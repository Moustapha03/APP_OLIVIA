{{-- PDF (dompdf): plain HTML + inline CSS, no Tailwind. Printed or kept offline. --}}
@php
    $svg = (string) \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(300)->margin(1)->errorCorrection('M')->generate($qr);
    $src = 'data:image/svg+xml;base64,'.base64_encode($svg);
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $isSubscription ? 'Abonnement' : 'Ticket' }} – {{ config('app.name') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; margin: 0; }
        .card { border: 2px dashed #94a3b8; border-radius: 16px; padding: 24px; text-align: center; }
        .kind { font-size: 13px; letter-spacing: 1px; text-transform: uppercase; color: #1d4ed8; font-weight: bold; }
        h1 { font-size: 30px; margin: 8px 0 4px; }
        .muted { color: #475569; font-size: 14px; margin: 2px 0; }
        .qr { margin: 20px auto; width: 300px; height: 300px; }
        .name { font-size: 18px; font-weight: bold; margin-top: 8px; }
        .foot { margin-top: 16px; font-size: 11px; color: #64748b; }
    </style>
</head>
<body>
    <div class="card">
        <div class="kind">{{ config('app.name') }} · {{ $isSubscription ? 'Abonnement mensuel' : 'Ticket journalier' }}</div>
        @if ($isSubscription)
            <h1>{{ ucfirst($pass->monthLabel()) }}</h1>
            <p class="name">{{ $pass->subscriber->full_name }}</p>
            <p class="muted">{{ $pass->formula->shortLabel() }} · vers {{ $pass->zone->line->destination_name }}</p>
            <p class="muted">Montée : {{ $pass->stop?->name ?? $pass->zone->name }} (zone {{ $pass->zone->name }} et plus proches)</p>
        @else
            <h1>{{ $pass->zone->name }}</h1>
            <p class="muted">Valable le {{ $pass->valid_on->format('d/m/Y') }} jusqu’à 23 h 59</p>
            <p class="muted">{{ $pass->rides_allowed }} trajets</p>
        @endif

        <img class="qr" src="{{ $src }}" alt="QR code">

        <p class="muted">Prix payé : {{ \App\Support\Money::format($pass->price_paid) }}</p>
        <p class="foot">Montrez ce QR code au contrôleur. {{ $isSubscription ? 'Titre personnel : pièce ou photo vérifiée au contrôle.' : '' }}</p>
    </div>
</body>
</html>
