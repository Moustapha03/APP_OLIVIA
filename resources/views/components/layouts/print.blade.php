{{--
    Thermal printer page (58 or 80 mm roll). On screen: the receipt in a
    frame with "Imprimer" / "Retour" buttons. On paper: the receipt only.

    <x-layouts.print title="Ticket" :width="80" :auto-print="true" :back="route(…)">
        <x-slot:actions>(more screen-only buttons)</x-slot:actions> …
    </x-layouts.print>
--}}
@props([
    'title' => 'Impression',
    'width' => 80,
    'autoPrint' => false,
    'back' => null,
])

<x-layouts.base :title="$title" body-class="bg-slate-100 text-black print:bg-white">
    <x-slot:head>
        {{-- @page cannot be set by a class; this nonce'd block is allowed by the CSP. --}}
        <style nonce="{{ Illuminate\Support\Facades\Vite::cspNonce() }}">
            @page { size: {{ (int) $width }}mm auto; margin: 0; }
        </style>
    </x-slot:head>

    <main id="main" class="flex min-h-dvh flex-col items-center gap-6 px-4 py-8 print:block print:p-0" @if ($autoPrint) data-auto-print @endif>
        <div class="flex w-full max-w-sm flex-col gap-3 print:hidden">
            <x-button type="button" size="lg" block icon="printer" data-print>Imprimer</x-button>
            {{ $actions ?? '' }}
            @if ($back)
                <x-button :href="$back" variant="secondary" block icon="chevron-left">Retour</x-button>
            @endif
        </div>

        <div @class([
            'bg-white px-[3mm] py-[4mm] font-mono text-[11pt] leading-snug shadow-soft print:shadow-none',
            'w-[58mm]' => (int) $width === 58,
            'w-[80mm]' => (int) $width !== 58,
        ])>
            {{ $slot }}
        </div>
    </main>
</x-layouts.base>
