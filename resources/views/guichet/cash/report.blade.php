@php
    use App\Enums\PaymentStatus;
    use App\Support\Money;
    $diff = $cashSession->difference;
@endphp

<x-layouts.print title="Rapport de caisse" :width="$width" :back="route('guichet.cash.show')">
    <div class="space-y-2 text-center">
        <p class="text-[13pt] font-bold">{{ config('app.name') }}</p>
        <p class="font-bold">RAPPORT DE CAISSE</p>
        <p>{{ $cashSession->user?->name }}</p>
    </div>

    <hr class="my-2 border-dashed border-black">

    <dl class="space-y-0.5">
        <div class="flex justify-between gap-2"><dt>Ouverture</dt><dd>{{ $cashSession->opened_at->format('d/m/Y H:i') }}</dd></div>
        <div class="flex justify-between gap-2"><dt>Clôture</dt><dd>{{ $cashSession->closed_at?->format('d/m/Y H:i') ?? 'en cours' }}</dd></div>
        <div class="flex justify-between gap-2"><dt>Abonnements</dt><dd>{{ $totals->subscriptionCount }}</dd></div>
        <div class="flex justify-between gap-2"><dt>Tickets du jour</dt><dd>{{ $totals->ticketCount }}</dd></div>
        @if ($totals->discountCount > 0)
            <div class="flex justify-between gap-2"><dt>Dont prix négociés</dt><dd>{{ $totals->discountCount }}</dd></div>
        @endif
    </dl>

    <hr class="my-2 border-dashed border-black">

    <p class="font-bold">ESPÈCES</p>
    <dl class="space-y-0.5">
        <div class="flex justify-between gap-2"><dt>Fond de caisse</dt><dd>{{ Money::format($totals->openingFloat) }}</dd></div>
        <div class="flex justify-between gap-2"><dt>Ventes en espèces</dt><dd>{{ Money::format($totals->cashAmount) }}</dd></div>
        <div class="flex justify-between gap-2 font-bold"><dt>Attendu</dt><dd>{{ Money::format($cashSession->expected_amount ?? $totals->expected()) }}</dd></div>
        @if ($cashSession->counted_amount !== null)
            <div class="flex justify-between gap-2 font-bold"><dt>Compté</dt><dd>{{ Money::format($cashSession->counted_amount) }}</dd></div>
            <div class="flex justify-between gap-2 font-bold"><dt>Écart</dt><dd>{{ $diff > 0 ? '+' : '' }}{{ Money::format((int) $diff) }}</dd></div>
        @endif
    </dl>

    @if ($totals->transferAmount > 0)
        <hr class="my-2 border-dashed border-black">
        <p class="font-bold">MOBILE MONEY (compte société)</p>
        <dl class="space-y-0.5">
            @foreach ($totals->transfersByMethod as $method => $amount)
                <div class="flex justify-between gap-2"><dt>{{ $method }}</dt><dd>{{ Money::format($amount) }}</dd></div>
            @endforeach
        </dl>
    @endif

    @if ($totals->cancelledCount > 0)
        <p class="mt-1">Annulé ({{ $totals->cancelledCount }}) : {{ Money::format($totals->cancelledAmount) }}</p>
    @endif

    @if ($cashSession->justification)
        <hr class="my-2 border-dashed border-black">
        <p class="font-bold">Explication de l’écart :</p>
        <p class="break-words">{{ $cashSession->justification }}</p>
    @endif

    <hr class="my-2 border-dashed border-black">

    <p class="font-bold">Détail</p>
    <ul class="space-y-1">
        @forelse ($sales as $sale)
            <li>
                <div class="flex justify-between gap-2">
                    <span>{{ $sale->receipt_number }}</span>
                    <span>{{ Money::format($sale->amount, false) }}{{ $sale->status === PaymentStatus::Cancelled ? ' ANNULÉ' : '' }}</span>
                </div>
                <div class="text-[9pt]">{{ $sale->subscriber ? \Illuminate\Support\Str::limit($sale->subscriber->full_name, 22) : 'Ticket '.$sale->zone->name }} · {{ $sale->purpose->shortLabel() }} · {{ $sale->payment_method?->label() }}{{ $sale->isDiscounted() ? ' · négocié' : '' }}</div>
            </li>
        @empty
            <li>Aucune vente.</li>
        @endforelse
    </ul>

    <hr class="my-2 border-dashed border-black">
    <p class="pt-6">Signature agent : ..................</p>
    <p class="pt-6">Signature responsable : ..........</p>
</x-layouts.print>
