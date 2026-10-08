{{--
    Data table. Scrolls sideways on small screens; th/td are styled here,
    so rows stay plain HTML.

    <x-table caption="Ventes du jour" empty="Aucune vente aujourd'hui.">
        <x-slot:head><th>Heure</th><th>Client</th><th class="text-right">Montant</th></x-slot:head>
        @foreach ($sales as $sale)
            <tr><td>…</td><td>…</td><td class="text-right"><x-money :amount="$sale->price" /></td></tr>
        @endforeach
        <x-slot:footer>{{ $sales->links() }}</x-slot:footer>
    </x-table>
--}}
@props([
    'caption' => null,
    'empty' => 'Rien à afficher pour le moment.',
])

<div {{ $attributes->class('overflow-hidden rounded-card bg-white shadow-soft ring-1 ring-slate-200/60') }}>
    {{-- relative: keeps absolutely positioned children (sr-only labels) inside the scroll box --}}
    <div class="relative overflow-x-auto">
        <table class="w-full min-w-[36rem] text-left text-base
            [&_th]:whitespace-nowrap [&_th]:px-4 [&_th]:py-3 [&_th]:text-sm [&_th]:font-semibold [&_th]:text-slate-600
            [&_td]:px-4 [&_td]:py-3.5 [&_td]:align-middle [&_td]:text-slate-800
            [&_tbody_tr]:border-t [&_tbody_tr]:border-slate-100 [&_tbody_tr:hover]:bg-slate-50/70">
            @if ($caption)
                <caption class="sr-only">{{ $caption }}</caption>
            @endif
            @isset($head)
                <thead class="bg-slate-50">
                    <tr>{{ $head }}</tr>
                </thead>
            @endisset
            <tbody>
                @if ($slot->isEmpty())
                    <tr>
                        <td colspan="100" class="py-12! text-center">
                            <div class="flex flex-col items-center gap-2 text-slate-500">
                                <x-lucide-inbox class="size-8" aria-hidden="true" />
                                <span>{{ $empty }}</span>
                            </div>
                        </td>
                    </tr>
                @else
                    {{ $slot }}
                @endif
            </tbody>
        </table>
    </div>

    @isset($footer)
        <div class="border-t border-slate-100 px-4 py-3">{{ $footer }}</div>
    @endisset
</div>
