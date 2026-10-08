@php use App\Enums\FareType; @endphp

<x-layouts.espace title="Acheter · Point de montée">
    <div class="space-y-6">
        <x-page-header title="Acheter un titre" :back="route('espace.home')" />

        <x-stepper :steps="['Montée', 'Formule', 'Paiement']" :current="1" />

        @php $hasOffer = false; @endphp
        @foreach ($lines as $line)
            <h2 class="text-xl font-semibold text-slate-900">Où montez-vous ? <span class="block text-sm font-normal text-slate-600">Direction {{ $line->destination_name }}</span></h2>

            @foreach ($line->zones->sortByDesc('position') as $zone)
                @php
                    $daily = $zone->fares->firstWhere('type', FareType::Daily);
                    $monthly = $zone->fares->firstWhere('type', FareType::Monthly);
                @endphp
                @continue((! $daily && ! $monthly) || $zone->stops->isEmpty())
                @php $hasOffer = true; @endphp

                <section class="space-y-2" aria-labelledby="zone-{{ $zone->id }}">
                    <p id="zone-{{ $zone->id }}" class="text-sm font-semibold text-slate-700">
                        {{ $zone->name }}
                        <span class="font-normal text-slate-600">
                            @if ($daily) · jour <x-money :amount="$daily->amount" /> @endif
                            @if ($monthly) · mois <x-money :amount="$monthly->amount" /> @endif
                        </span>
                    </p>
                    <ul class="grid gap-2 sm:grid-cols-2">
                        @foreach ($zone->stops as $stop)
                            <li>
                                <a href="{{ route('espace.purchase.formula', $stop) }}" class="flex min-h-14 items-center gap-3 rounded-2xl bg-white px-4 shadow-soft ring-1 ring-slate-200/70 transition hover:ring-primary-300 active:scale-[0.99]">
                                    <x-lucide-map-pin class="size-5 shrink-0 text-primary-600" aria-hidden="true" />
                                    <span class="flex-1 text-base font-semibold text-slate-900">{{ $stop->name }}</span>
                                    <x-lucide-chevron-right class="size-5 shrink-0 text-slate-400" aria-hidden="true" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        @endforeach

        @unless ($hasOffer)
            <x-empty-state icon="map-pin" title="Rien en vente pour le moment">
                Revenez un peu plus tard ou passez à l’agence.
            </x-empty-state>
        @endunless
    </div>
</x-layouts.espace>
