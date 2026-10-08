<?php

namespace App\Services\Receipts;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Services\Qr\PassSigner;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Receipt as a PDF file, and how to send it to the client on WhatsApp.
 *
 * A wa.me link can only carry text: the message holds a signed link to
 * the PDF. The link needs no account and expires a week after the pass
 * ends (it contains the QR code, i.e. the pass itself).
 */
class ReceiptSharing
{
    public const LINK_DAYS_AFTER_PASS = 7;

    public function __construct(private readonly PassSigner $signer) {}

    /**
     * Receipts that can be shared: paid, with their pass.
     */
    public function canShare(Payment $payment): bool
    {
        return $payment->isSucceeded() && $payment->receipt_number !== null && $payment->payable !== null;
    }

    /**
     * @param  bool  $inline  true: opened in the browser (shared link), false: downloaded
     */
    public function pdf(Payment $payment, bool $inline = false): Response
    {
        $payment->loadMissing(['zone.line', 'stop', 'seller', 'subscriber', 'user', 'payable']);
        /** @var Ticket|Subscription $pass */
        $pass = $payment->payable;

        $pdf = Pdf::loadView('receipts.pdf', [
            'payment' => $payment,
            'pass' => $pass,
            'isSubscription' => $pass instanceof Subscription,
            'qr' => $this->signer->sign($pass),
        ])->setPaper('a5')->setOption('isFontSubsettingEnabled', true);

        return $inline ? $pdf->stream($this->fileName($payment)) : $pdf->download($this->fileName($payment));
    }

    public function fileName(Payment $payment): string
    {
        return 'recu-'.$payment->receipt_number.'.pdf';
    }

    /**
     * Link to the PDF, valid without an account until a week after the pass ends.
     */
    public function sharedUrl(Payment $payment): string
    {
        return URL::temporarySignedRoute('receipts.shared', $this->linkExpiry($payment), ['payment' => $payment->reference]);
    }

    public function linkExpiry(Payment $payment): Carbon
    {
        $pass = $payment->payable;
        $end = match (true) {
            $pass instanceof Subscription => $pass->ends_on,
            $pass instanceof Ticket => $pass->valid_on,
            default => today(),
        };

        return $end->copy()->endOfDay()->addDays(self::LINK_DAYS_AFTER_PASS);
    }

    /**
     * wa.me link: opens WhatsApp on the client's number (or the contact
     * picker without a number), with the message ready to send.
     */
    public function whatsappUrl(Payment $payment): string
    {
        $phone = $payment->subscriber->phone ?? $payment->user?->phone;
        $digits = $phone !== null ? (string) preg_replace('/\D+/', '', $phone) : '';

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($this->message($payment));
    }

    public function message(Payment $payment): string
    {
        $payment->loadMissing(['subscriber', 'user', 'payable']);
        $pass = $payment->payable;
        $name = $payment->subscriber->full_name ?? $payment->user?->name;

        $what = $pass instanceof Subscription
            ? 'Abonnement '.$payment->purpose->shortLabel().' – '.$pass->monthLabel()
            : 'Ticket du jour – '.($pass instanceof Ticket ? $pass->valid_on->format('d/m/Y') : '');

        return implode("\n", [
            'Bonjour'.($name ? ' '.$name : '').',',
            'Voici votre reçu N° '.$payment->receipt_number.' ('.$what.', '.Money::format($payment->amount).').',
            'Téléchargez-le ici : '.$this->sharedUrl($payment),
            'Montrez le QR code au convoyeur. Merci de voyager avec '.config('app.name').' !',
        ]);
    }
}
