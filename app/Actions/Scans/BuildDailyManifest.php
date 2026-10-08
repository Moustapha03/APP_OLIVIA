<?php

namespace App\Actions\Scans;

use App\Enums\Period;
use App\Models\Scan;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * List of the passes valid today, downloaded by the controller's phone so
 * it can check passes without network on the bus (step 7).
 *
 * Data kept to the minimum: no photo, no phone number, no signing key
 * (the QR signature is checked by the server when the scans are synced).
 * The holder's name and the subscription receipt number stay: offline, the
 * name is compared with an ID card (no photo) and a subscription number may
 * be typed by hand; ticket numbers are left out (typed tickets are refused).
 * Never cached by the service worker (/api) and erased at logout or when
 * the account changes (resources/js/pwa.js).
 * The uuid inside the QR code is random (v4): a forged code does not
 * match any entry.
 */
class BuildDailyManifest
{
    /**
     * @return array{
     *     user_id: int,
     *     generated_at: string,
     *     valid_for: string,
     *     evening_starts_at: string,
     *     anti_replay_minutes: int,
     *     passes: list<array<string, mixed>>
     * }
     */
    public function handle(User $controller, ?Carbon $now = null): array
    {
        $now ??= now();
        $today = $now->copy()->startOfDay();
        $passes = [];
        [$lastTicketScans, $lastSubscriptionScans] = $this->recentlyAccepted($now);

        Subscription::query()
            ->running($today)
            ->whereDate('starts_on', '<=', $today->toDateString())
            ->with(['subscriber', 'zone', 'stop', 'payment'])
            ->orderBy('id')
            ->chunk(500, function ($subscriptions) use (&$passes, $lastSubscriptionScans): void {
                /** @var Subscription $subscription */
                foreach ($subscriptions as $subscription) {
                    $passes[] = [
                        'kind' => 's',
                        'uuid' => strtolower($subscription->uuid),
                        'receipt_number' => $subscription->payment?->receipt_number,
                        'holder' => $subscription->subscriber->full_name,
                        'formula' => $subscription->formula->shortLabel(),
                        'periods' => self::periods($subscription->formula->periods()),
                        'zone' => $subscription->zone->name,
                        'boarding_point' => $subscription->stop?->name,
                        'valid' => $subscription->monthLabel(),
                        'rides_allowed' => null,
                        'rides_used' => null,
                        'has_photo' => $subscription->subscriber->hasPhoto(),
                        'last_accepted_at' => $lastSubscriptionScans[$subscription->id] ?? null,
                    ];
                }
            });

        Ticket::query()
            ->validOn($today)
            ->whereColumn('rides_used', '<', 'rides_allowed')
            ->with(['zone', 'stop'])
            ->orderBy('id')
            ->chunk(500, function ($tickets) use (&$passes, $lastTicketScans): void {
                /** @var Ticket $ticket */
                foreach ($tickets as $ticket) {
                    $passes[] = [
                        'kind' => 't',
                        'uuid' => strtolower($ticket->uuid),
                        // Ticket numbers typed by hand are refused: not needed.
                        'receipt_number' => null,
                        'holder' => null,
                        'formula' => 'Ticket du jour',
                        'periods' => self::periods([Period::Morning, Period::Evening]),
                        'zone' => $ticket->zone->name,
                        'boarding_point' => $ticket->stop?->name,
                        'valid' => $ticket->valid_on->format('d/m/Y'),
                        'rides_allowed' => $ticket->rides_allowed,
                        'rides_used' => $ticket->rides_used,
                        'has_photo' => false,
                        'last_accepted_at' => $lastTicketScans[$ticket->id] ?? null,
                    ];
                }
            });

        return [
            'user_id' => $controller->id,
            'generated_at' => $now->toIso8601String(),
            'valid_for' => $today->toDateString(),
            'evening_starts_at' => (string) config('transport.scans.evening_starts_at', '12:00'),
            'anti_replay_minutes' => (int) config('transport.scans.anti_replay_minutes', 30),
            'passes' => $passes,
        ];
    }

    /**
     * Last accepted scan of each pass within the anti-replay window, by any
     * controller: the phone warns "déjà scanné" offline too.
     *
     * @return array{array<int, string>, array<int, string>} ticket id => ISO time, subscription id => ISO time
     */
    private function recentlyAccepted(Carbon $now): array
    {
        $minutes = (int) config('transport.scans.anti_replay_minutes', 30);
        $tickets = [];
        $subscriptions = [];

        if ($minutes <= 0) {
            return [$tickets, $subscriptions];
        }

        $scans = Scan::query()->accepted()
            ->where('scanned_at', '>=', $now->copy()->subMinutes($minutes))
            ->where('scanned_at', '<=', $now)
            ->orderBy('scanned_at')
            ->get(['ticket_id', 'subscription_id', 'scanned_at']);

        foreach ($scans as $scan) {
            if ($scan->ticket_id !== null) {
                $tickets[$scan->ticket_id] = $scan->scanned_at->toIso8601String();
            }
            if ($scan->subscription_id !== null) {
                $subscriptions[$scan->subscription_id] = $scan->scanned_at->toIso8601String();
            }
        }

        return [$tickets, $subscriptions];
    }

    /**
     * @param  list<Period>  $periods
     * @return list<string>
     */
    private static function periods(array $periods): array
    {
        return array_map(fn (Period $period): string => $period->value, $periods);
    }
}
