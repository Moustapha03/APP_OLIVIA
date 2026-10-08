@php
    use App\Enums\PaymentStatus;
    use App\Models\Subscription;
    use App\Support\Money;
    $isSubscription = $pass instanceof Subscription;
    $cancelled = $payment->status === PaymentStatus::Cancelled;
    $qrSize = $width === 58 ? 150 : 210;
    $back ??= $isSubscription ? route('guichet.subscriptions.create') : route('guichet.sales.create');
    $paidAt = $payment->confirmed_at ?? $payment->created_at;
    $client = $payment->subscriber?->full_name ?? $payment->user?->name;
    $phone = $payment->subscriber?->formatted_phone ?: \App\Support\PhoneNumber::format($payment->user?->phone);
@endphp

<x-layouts.print title="Reçu {{ $payment->receipt_number }}" :width="$width" :auto-print="$autoPrint && ! $cancelled" :back="$back">
    @if (! $cancelled && $qr && $payment->receipt_number)
        <x-slot:actions>
            @include('receipts._share-buttons', ['block' => true, 'whatsappTo' => $phone ?: null])
        </x-slot:actions>
    @endif

    <div class="space-y-1 text-center">
        <p class="text-[13pt] font-bold">{{ config('app.name') }}</p>
        <p>{{ $payment->zone->line->name }}</p>
        <p class="font-bold">Reçu N° {{ $payment->receipt_number }}</p>
    </div>

    <hr class="my-2 border-dashed border-black">

    <p class="text-center text-[12pt] font-bold">{{ $isSubscription ? 'ABONNEMENT '.mb_strtoupper($payment->purpose->shortLabel()) : 'TICKET DU JOUR' }}</p>

    {{-- One line per piece of information, label on the left. --}}
    <dl class="mt-2 space-y-1">
        <div class="flex justify-between gap-2"><dt>Client</dt><dd class="text-right font-bold">{{ $client ?? 'Client de passage' }}</dd></div>
        @if ($phone)
            <div class="flex justify-between gap-2"><dt>Téléphone</dt><dd class="text-right font-bold tabular-nums">{{ $phone }}</dd></div>
        @endif
        <div class="flex justify-between gap-2"><dt>Type</dt><dd class="text-right font-bold">{{ $isSubscription ? 'Abonnement mensuel '.$payment->purpose->shortLabel() : 'Ticket du jour (matin et soir)' }}</dd></div>
        @if ($isSubscription)
            <div class="flex justify-between gap-2"><dt>Mois couvert</dt><dd class="text-right font-bold">{{ ucfirst($pass->monthLabel()) }}</dd></div>
            <div class="flex justify-between gap-2"><dt>Valable</dt><dd class="text-right">du {{ $pass->starts_on->format('d/m/Y') }} au {{ $pass->ends_on->format('d/m/Y') }}</dd></div>
        @else
            <div class="flex justify-between gap-2"><dt>Valable le</dt><dd class="text-right font-bold">{{ $pass->valid_on->format('d/m/Y') }} (jusqu’à 23h59)</dd></div>
        @endif
        <div class="flex justify-between gap-2"><dt>Montée</dt><dd class="text-right">{{ $payment->stop?->name ?? $payment->zone->name }}</dd></div>
        <div class="flex justify-between gap-2"><dt>Zone</dt><dd class="text-right">{{ $payment->zone->name }}{{ $isSubscription ? ' et zones plus proches' : '' }}</dd></div>
    </dl>

    <hr class="my-2 border-dashed border-black">

    <dl class="space-y-1">
        <div class="flex justify-between gap-2 text-[12pt] font-bold"><dt>Montant payé</dt><dd class="whitespace-nowrap">{{ Money::format($payment->amount) }}</dd></div>
        <div class="flex justify-between gap-2"><dt>Date de paiement</dt><dd class="text-right tabular-nums">{{ $paidAt->format($payment->gateway === 'import' ? 'd/m/Y' : 'd/m/Y H:i') }}</dd></div>
        <div class="flex justify-between gap-2"><dt>Paiement</dt><dd>{{ $payment->payment_method?->label() }}</dd></div>
        @if ($payment->external_reference)
            <div class="flex justify-between gap-2"><dt>Réf.</dt><dd class="break-all text-right">{{ $payment->external_reference }}</dd></div>
        @endif
        @if ($payment->seller)
            <div class="flex justify-between gap-2"><dt>Agent</dt><dd>{{ $payment->seller->first_name }}</dd></div>
        @endif
    </dl>

    @if ($cancelled)
        <p class="my-4 border-2 border-black py-2 text-center text-[16pt] font-bold">ANNULÉ</p>
    @elseif ($qr)
        <div class="my-3 flex justify-center">
            <x-qr :payload="$qr" :size="$qrSize" label="QR code du titre" class="rounded-none p-0 ring-0" />
        </div>
    @endif

    <hr class="my-2 border-dashed border-black">
    <p class="text-center text-[9pt]">Montrez ce QR code au convoyeur. Conservez ce reçu.</p>
</x-layouts.print>
