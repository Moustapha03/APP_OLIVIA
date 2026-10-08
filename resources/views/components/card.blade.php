{{--
    <x-card title="Mon abonnement" subtitle="Valable jusqu'au 4 novembre">
        <x-slot:actions><x-badge variant="success">Actif</x-badge></x-slot:actions>
        …
        <x-slot:footer><x-button block>Renouveler</x-button></x-slot:footer>
    </x-card>

    padding=false for edge-to-edge content (tables, lists).
--}}
@props([
    'title' => null,
    'subtitle' => null,
    'padding' => true,
])

<section {{ $attributes->class('overflow-hidden rounded-card bg-white shadow-soft ring-1 ring-slate-200/60') }}>
    @if ($title || isset($actions))
        <header class="flex items-start justify-between gap-4 px-6 pt-6 {{ $padding ? '' : 'pb-4' }}">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="text-lg font-medium tracking-tight text-slate-950">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div @class(['p-6' => $padding, 'pt-4' => $padding && $title])>
        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="border-t border-slate-100 bg-slate-50/60 px-6 py-4">
            {{ $footer }}
        </footer>
    @endisset
</section>
