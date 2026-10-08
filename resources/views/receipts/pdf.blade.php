{{-- Receipt PDF (dompdf): plain HTML + inline CSS, no Tailwind. Sent to the client on WhatsApp. --}}
@php
    use App\Support\Money;
    use App\Support\PhoneNumber;
    $svg = (string) \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(200)->margin(1)->errorCorrection('M')->generate($qr);
    $src = 'data:image/svg+xml;base64,'.base64_encode($svg);
    $paidAt = $payment->confirmed_at ?? $payment->created_at;
    $client = $payment->subscriber->full_name ?? $payment->user?->name;
    $phone = $payment->subscriber?->formatted_phone ?: PhoneNumber::format($payment->user?->phone);
    $rows = array_filter([
        'Client' => $client ?? 'Client de passage',
        'Téléphone' => $phone ?: null,
        'Type' => $isSubscription ? 'Abonnement mensuel '.$payment->purpose->shortLabel() : 'Ticket du jour (matin et soir)',
        'Mois couvert' => $isSubscription ? ucfirst($pass->monthLabel()) : null,
        'Valable' => $isSubscription
            ? 'du '.$pass->starts_on->format('d/m/Y').' au '.$pass->ends_on->format('d/m/Y')
            : 'le '.$pass->valid_on->format('d/m/Y').' (jusqu’à 23h59)',
        'Montée' => $payment->stop?->name ?? $payment->zone->name,
        'Zone' => $payment->zone->name.($isSubscription ? ' et zones plus proches' : ''),
    ]);
    $paymentRows = array_filter([
        'Date de paiement' => $paidAt->format($payment->gateway === 'import' ? 'd/m/Y' : 'd/m/Y à H:i'),
        'Paiement' => $payment->payment_method?->label(),
        'Réf. du transfert' => $payment->external_reference,
    ]);
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu {{ $payment->receipt_number }} – {{ config('app.name') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; margin: 0; font-size: 12px; }
        .card { border: 2px dashed #94a3b8; border-radius: 14px; padding: 14px 20px; }
        .center { text-align: center; }
        .brand { font-size: 16px; font-weight: bold; }
        .muted { color: #475569; }
        .number { font-size: 15px; font-weight: bold; margin: 6px 0 0; }
        .kind { margin: 10px 0 6px; padding: 6px; background: #eff6ff; color: #1d4ed8; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; text-align: center; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 0; vertical-align: top; }
        td.label { color: #475569; width: 38%; }
        td.value { font-weight: bold; text-align: right; }
        .total td { font-size: 16px; padding-top: 8px; border-top: 1px dashed #94a3b8; }
        .qr { margin: 8px auto 2px; width: 165px; height: 165px; }
        .foot { margin-top: 6px; font-size: 10px; color: #64748b; text-align: center; }
        .cancelled { border: 3px solid #b91c1c; color: #b91c1c; font-size: 22px; font-weight: bold; text-align: center; padding: 8px; margin: 12px 0; }
    </style>
</head>
<body>
    <div class="card">
        <div class="center">
            <div class="brand">{{ config('app.name') }}</div>
            <div class="muted">{{ $payment->zone->line->name }}</div>
            <p class="number">Reçu N° {{ $payment->receipt_number }}</p>
        </div>

        <div class="kind">{{ $isSubscription ? 'Abonnement '.$payment->purpose->shortLabel() : 'Ticket du jour' }}</div>

        <table>
            @foreach ($rows as $label => $value)
                <tr><td class="label">{{ $label }}</td><td class="value">{{ $value }}</td></tr>
            @endforeach
            <tr class="total"><td class="label">Montant payé</td><td class="value">{{ Money::format($payment->amount) }}</td></tr>
            @foreach ($paymentRows as $label => $value)
                <tr><td class="label">{{ $label }}</td><td class="value">{{ $value }}</td></tr>
            @endforeach
        </table>

        <div class="center"><img class="qr" src="{{ $src }}" alt="QR code"></div>

        <p class="foot">Montrez ce QR code au convoyeur. Conservez ce reçu.{{ $isSubscription ? ' Titre personnel : la photo est vérifiée au contrôle.' : '' }}</p>
    </div>
</body>
</html>
