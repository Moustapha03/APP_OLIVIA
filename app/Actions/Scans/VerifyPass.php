<?php

namespace App\Actions\Scans;

use App\Enums\PassStatus;
use App\Enums\Period;
use App\Enums\ScanResult;
use App\Models\Payment;
use App\Models\Scan;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Qr\PassSigner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * On-board check of a pass (step 7), from the QR code content or a receipt
 * number typed by hand ("BA-2026-0017", "tj-2026-1").
 *
 * - unknown / forged code, cancelled or refunded pass → refused;
 * - subscription: must cover today (calendar month) and the current period
 *   (morning before transport.scans.evening_starts_at, evening after);
 *   no photo on file → warning (check an ID card);
 * - daily ticket: valid on its day only, one ride counted per accepted scan,
 *   refused once every ride is used;
 * - anti-replay: a pass already accepted within anti_replay_minutes, by any
 *   controller, gives a warning and no ride is counted again.
 * Every check is recorded in `scans`. The pass row is locked: two
 * controllers scanning the same ticket at once count one ride only.
 *
 * Receipt number typed by hand (no signed QR code): subscriptions only, the
 * controller compares the holder with the name and photo shown (warning
 * screen); a daily ticket is refused ("manual_entry"): its QR code must be
 * scanned. Every typed entry is written to the audit log (scan.manual_entry).
 *
 * The photo is shown through a signed URL valid 5 minutes, made for the pass
 * just scanned: controllers cannot browse photos by id (SubscriberPolicy).
 *
 * Offline scans (OfflineScan, synced afterwards) go through the same checks
 * at the time of the scan. When the phone refused a passenger the server
 * would have accepted, the scan is recorded as refused ("offline_refused")
 * and no ride is counted: the passenger did not board. An offline ticket
 * scan received after the ticket's day counts no ride ("offline_late").
 */
class VerifyPass
{
    public const RECEIPT_PATTERN = '/^(BA|TJ)-?(\d{4})-?(\d{1,6})$/';

    /**
     * How long the photo link shown with a verdict stays valid.
     */
    public const PHOTO_URL_MINUTES = 5;

    public const MANUAL_TICKET_REASON = 'Ticket du jour : scannez le QR code du reçu (saisie du numéro non acceptée)';

    public function __construct(private readonly PassSigner $signer) {}

    public function handle(User $controller, string $code, ?OfflineScan $offline = null): PassCheck
    {
        $now = $offline !== null ? $offline->scannedAt->copy() : now();
        $period = self::periodAt($now);
        $code = trim($code);

        $receipt = null;
        $manual = ! str_starts_with($code, PassSigner::VERSION.'.');
        $pass = $manual
            ? $this->findByReceipt($code, $receipt)
            : $this->signer->verify($code)?->find();

        if ($pass === null) {
            return $this->unknown($controller, $code, $receipt, $period, $now, $offline);
        }

        if ($manual && $pass instanceof Ticket) {
            return $this->manualTicket($controller, $pass, $receipt, $period, $now, $offline);
        }

        $check = DB::transaction(fn (): PassCheck => $pass instanceof Ticket
            ? $this->checkTicket($controller, $pass, $period, $now, $offline)
            : $this->checkSubscription($controller, $pass, $period, $now, $offline, $manual));

        if ($manual) {
            $this->logManualEntry($controller, $check->scan, $receipt, $offline);
        }

        return $check;
    }

    /**
     * Trip of the day a scan belongs to.
     */
    public static function periodAt(Carbon $at): Period
    {
        $eveningStartsAt = $at->copy()->setTimeFromTimeString((string) config('transport.scans.evening_starts_at', '12:00'));

        return $at->lt($eveningStartsAt) ? Period::Morning : Period::Evening;
    }

    /**
     * "ba 2026 17" → BA-2026-0017 (subscriptions: 4 digits, tickets: 5).
     */
    public static function normalizeReceipt(string $input): string
    {
        $compact = (string) preg_replace('/[\s_.\/]+/', '-', mb_strtoupper(trim($input)));

        if (preg_match(self::RECEIPT_PATTERN, $compact, $m) !== 1) {
            return $compact;
        }

        return sprintf('%s-%s-%0'.($m[1] === 'BA' ? 4 : 5).'d', $m[1], $m[2], (int) $m[3]);
    }

    /**
     * "de septembre 2026", "d’octobre 2026".
     */
    private static function of(string $month): string
    {
        return (preg_match('/^[aeiouy]/i', $month) === 1 ? 'd’' : 'de ').$month;
    }

    /**
     * @param-out Payment|null $payment
     */
    private function findByReceipt(string $code, ?Payment &$payment): Ticket|Subscription|null
    {
        $payment = $code === '' ? null : Payment::query()->where('receipt_number', self::normalizeReceipt($code))->first();
        $pass = $payment?->payable;

        return $pass instanceof Ticket || $pass instanceof Subscription ? $pass : null;
    }

    private function unknown(User $controller, string $code, ?Payment $payment, Period $period, Carbon $now, ?OfflineScan $offline): PassCheck
    {
        [$reasonCode, $reason] = match (true) {
            $payment !== null => ['no_pass', 'Aucun titre pour le reçu '.$payment->receipt_number.' (paiement non confirmé ou annulé)'],
            str_starts_with($code, PassSigner::VERSION.'.') || preg_match(self::RECEIPT_PATTERN, self::normalizeReceipt($code)) !== 1 => ['unknown', 'Code inconnu ou falsifié'],
            default => ['unknown', 'Reçu '.self::normalizeReceipt($code).' introuvable'],
        };

        $scan = $this->record($controller, Scan::KIND_UNKNOWN, null, ScanResult::Refused, $reasonCode, $reason, $period, $now, $offline);

        // A forged QR code may be fraud: keep a trace in the audit log.
        activity('scans')->causedBy($controller)->performedOn($scan)
            ->event('scan.unknown')
            ->withProperties(array_filter([
                'code' => mb_substr($code, 0, 200),
                'offline_result' => $offline?->deviceResult->value,
            ]))
            ->log($reason);

        return new PassCheck(ScanResult::Refused, 'Code inconnu', $reason, $scan, receiptNumber: $payment?->receipt_number);
    }

    private function checkSubscription(User $controller, Subscription $subscription, Period $period, Carbon $now, ?OfflineScan $offline, bool $manual): PassCheck
    {
        /** @var Subscription $subscription */
        $subscription = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();
        $subscription->load(['subscriber', 'zone', 'stop', 'payment']);

        $formula = $subscription->formula->shortLabel();
        $month = $subscription->monthLabel();
        $today = $now->copy()->startOfDay();

        [$result, $reasonCode, $reason] = match (true) {
            $subscription->status !== PassStatus::Active => [ScanResult::Refused, $subscription->status->value, 'Abonnement '.mb_strtolower($subscription->status->label())],
            ! $subscription->hasStarted($today) => [ScanResult::Refused, 'not_started', 'Abonnement '.self::of($month).' : pas encore commencé'],
            $subscription->isExpired($today) => [ScanResult::Refused, 'expired', 'Abonnement '.self::of($month).' : expiré'],
            ! $subscription->coversPeriod($period) => [ScanResult::Refused, 'wrong_period', "Abonnement {$formula} : non valable le ".mb_strtolower($period->label())],
            default => $this->replay($controller, 'subscription_id', $subscription->id, $now)
                ?? ($subscription->subscriber->hasPhoto()
                    ? [ScanResult::Valid, 'ok', 'Valable jusqu’au '.$subscription->ends_on->translatedFormat('j F Y')]
                    : [ScanResult::Warning, 'photo_missing', 'Photo manquante : vérifiez une pièce d’identité']),
        };
        [$result, $reasonCode, $reason] = $this->onBoard($offline, $result, $reasonCode, $reason);

        // No signed QR code: the controller must compare the holder with the name and photo.
        if ($manual && in_array($reasonCode, Scan::ACCEPTED_CODES, true)) {
            $result = ScanResult::Warning;
            $reason = 'N° saisi à la main : vérifiez le nom et la photo · '.$reason;
        }

        $scan = $this->record($controller, Scan::KIND_SUBSCRIPTION, $subscription, $result, $reasonCode, $reason, $period, $now, $offline);
        $subscriber = $subscription->subscriber;

        return new PassCheck(
            status: $result,
            title: 'Abonnement '.$formula,
            reason: $reason,
            scan: $scan,
            holder: $subscriber->full_name,
            photoUrl: $subscriber->hasPhoto() ? self::photoUrl($subscriber->id) : null,
            formula: $formula,
            zone: $subscription->zone->name,
            boardingPoint: $subscription->stop?->name,
            validity: $month,
            receiptNumber: $subscription->payment?->receipt_number,
        );
    }

    private function checkTicket(User $controller, Ticket $ticket, Period $period, Carbon $now, ?OfflineScan $offline): PassCheck
    {
        /** @var Ticket $ticket */
        $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
        $ticket->load(['user', 'zone', 'stop', 'payment']);

        $day = $ticket->valid_on->translatedFormat('j F Y');

        [$result, $reasonCode, $reason] = match (true) {
            $ticket->status !== PassStatus::Active => [ScanResult::Refused, $ticket->status->value, 'Ticket '.mb_strtolower($ticket->status->label())],
            $ticket->valid_on->lt($now->copy()->startOfDay()) => [ScanResult::Refused, 'expired', "Ticket du {$day} : expiré"],
            ! $ticket->valid_on->isSameDay($now) => [ScanResult::Refused, 'not_started', "Ticket du {$day} : pas encore valable"],
            default => $this->replay($controller, 'ticket_id', $ticket->id, $now)
                ?? ($ticket->ridesLeft() === 0
                    ? [ScanResult::Refused, 'used_up', "Ticket déjà utilisé ({$ticket->rides_used}/{$ticket->rides_allowed} trajets)"]
                    : [ScanResult::Valid, 'ok', 'Trajet '.($ticket->rides_used + 1).' sur '.$ticket->rides_allowed]),
        };
        [$result, $reasonCode, $reason] = $this->onBoard($offline, $result, $reasonCode, $reason);

        // Offline scan received after the ticket's day (back-dated or sent
        // late): recorded, but no ride is counted any more.
        if ($offline !== null && $reasonCode === 'ok' && ! $ticket->valid_on->isSameDay(now())) {
            [$result, $reasonCode, $reason] = [ScanResult::Warning, 'offline_late', 'Scan hors ligne reçu après le jour du ticket : trajet non décompté'];
        }

        if ($reasonCode === 'ok') {
            $ticket->increment('rides_used');
        }

        $scan = $this->record($controller, Scan::KIND_TICKET, $ticket, $result, $reasonCode, $reason, $period, $now, $offline);

        return new PassCheck(
            status: $result,
            title: 'Ticket du jour',
            reason: $reason,
            scan: $scan,
            holder: $ticket->user?->name,
            formula: 'Ticket du jour',
            zone: $ticket->zone->name,
            boardingPoint: $ticket->stop?->name,
            validity: $day,
            rides: "{$ticket->rides_used}/{$ticket->rides_allowed}",
            receiptNumber: $ticket->payment?->receipt_number,
        );
    }

    /**
     * Short-lived signed link to the photo of the subscriber just scanned
     * (SubscriberPhotoController accepts it without the viewPhoto grant).
     */
    public static function photoUrl(int $subscriberId): string
    {
        return URL::temporarySignedRoute('photos.subscriber', now()->addMinutes(self::PHOTO_URL_MINUTES), ['subscriber' => $subscriberId], absolute: false);
    }

    /**
     * Daily ticket number typed by hand: refused, the signed QR code is
     * required (a receipt number can be read on someone else's receipt).
     */
    private function manualTicket(User $controller, Ticket $ticket, ?Payment $payment, Period $period, Carbon $now, ?OfflineScan $offline): PassCheck
    {
        $scan = $this->record($controller, Scan::KIND_TICKET, $ticket, ScanResult::Refused, 'manual_entry', self::MANUAL_TICKET_REASON, $period, $now, $offline);
        $this->logManualEntry($controller, $scan, $payment, $offline);

        return new PassCheck(ScanResult::Refused, 'Ticket du jour', self::MANUAL_TICKET_REASON, $scan, receiptNumber: $payment?->receipt_number);
    }

    /**
     * Every receipt number typed by hand is traced (audit of manual entries).
     */
    private function logManualEntry(User $controller, Scan $scan, ?Payment $payment, ?OfflineScan $offline): void
    {
        activity('scans')->causedBy($controller)->performedOn($scan)
            ->event('scan.manual_entry')
            ->withProperties(array_filter([
                'receipt_number' => $payment?->receipt_number,
                'result' => $scan->result->value,
                'reason_code' => $scan->reason_code,
                'source' => $offline === null ? Scan::SOURCE_ONLINE : Scan::SOURCE_OFFLINE,
            ]))
            ->log('N° de reçu saisi à la main');
    }

    /**
     * Warning when the same pass was accepted a moment ago (shared pass,
     * or the controller scanned twice).
     *
     * @return array{ScanResult, string, string}|null
     */
    private function replay(User $controller, string $column, int $passId, Carbon $now): ?array
    {
        $minutes = (int) config('transport.scans.anti_replay_minutes', 30);

        if ($minutes <= 0) {
            return null;
        }

        $previous = Scan::query()->accepted()
            ->where($column, $passId)
            ->where('scanned_at', '>=', $now->copy()->subMinutes($minutes))
            ->where('scanned_at', '<=', $now)
            ->with('user')
            ->latest('scanned_at')
            ->latest('id')
            ->first();

        if ($previous === null) {
            return null;
        }

        $by = match (true) {
            $previous->user_id === $controller->id => 'vous',
            $previous->user !== null => $previous->user->name,
            default => 'un autre contrôleur',
        };

        return [ScanResult::Warning, 'replay', 'Déjà scanné à '.$previous->scanned_at->format('H:i').' par '.$by];
    }

    /**
     * Offline scan the phone refused although the pass was good (sold after
     * the list of the day was downloaded…): the passenger did not board,
     * so it must not count as a ride nor trigger the anti-replay.
     *
     * @return array{ScanResult, string, string}
     */
    private function onBoard(?OfflineScan $offline, ScanResult $result, string $reasonCode, string $reason): array
    {
        if ($offline === null || $offline->deviceAccepted() || ! in_array($reasonCode, Scan::ACCEPTED_CODES, true)) {
            return [$result, $reasonCode, $reason];
        }

        return [ScanResult::Refused, 'offline_refused', 'Refusé à bord hors ligne (titre pourtant valable : '.$reason.')'];
    }

    private function record(User $controller, string $kind, Ticket|Subscription|null $pass, ScanResult $result, string $reasonCode, string $reason, Period $period, Carbon $now, ?OfflineScan $offline = null): Scan
    {
        $scan = new Scan;
        $scan->forceFill([
            'user_id' => $controller->id,
            'kind' => $kind,
            'ticket_id' => $pass instanceof Ticket ? $pass->id : null,
            'subscription_id' => $pass instanceof Subscription ? $pass->id : null,
            'result' => $result,
            'reason_code' => $reasonCode,
            'reason' => mb_substr($reason, 0, 255),
            'period' => $period,
            'scanned_at' => $now,
            ...($offline === null ? [] : [
                'source' => Scan::SOURCE_OFFLINE,
                'client_id' => $offline->clientId,
                'device_result' => $offline->deviceResult,
                'device_reason_code' => $offline->deviceReasonCode,
                'synced_at' => now(),
            ]),
        ])->save();

        return $scan;
    }
}
