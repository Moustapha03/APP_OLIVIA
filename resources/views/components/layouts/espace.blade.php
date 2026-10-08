{{--
    Customer space (/espace). Phone first: content column + bottom tab bar.
    From 768 px the tabs move into the header.

    <x-layouts.espace title="Mes titres">…</x-layouts.espace>
--}}
@props([
    'title' => null,
    'preview' => false,
])

@php
    $menu = \App\Support\Navigation::for('espace', auth()->user(), $preview);
    $items = $menu[''] ?? [];
@endphp

<x-layouts.base :title="$title">
    @isset($head)
        <x-slot:head>{{ $head }}</x-slot:head>
    @endisset

    <header class="sticky top-0 z-30 bg-slate-100/90 backdrop-blur">
        <div class="mx-auto flex h-20 max-w-3xl items-center justify-between gap-4 px-4">
            <x-brand />

            <nav class="hidden items-center gap-1 md:flex" aria-label="Menu principal">
                @foreach ($items as $item)
                    <a href="{{ $item['href'] }}" @if ($item['active']) aria-current="page" @endif @class([
                        'inline-flex min-h-11 items-center gap-2 rounded-full px-4 text-base transition',
                        'bg-slate-950 text-white' => $item['active'],
                        'text-slate-700 hover:bg-white' => ! $item['active'],
                    ])>
                        <x-dynamic-component :component="'lucide-'.$item['icon']" class="size-5" aria-hidden="true" />
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
        </div>
    </header>

    {{-- Offline: the passes opened before stay visible (service worker copy). --}}
    <div x-data="networkStatus" x-show="!online" x-cloak class="mx-auto max-w-lg px-4" role="status">
        <p class="flex items-center gap-3 rounded-full bg-slate-950 px-5 py-3 text-sm text-white">
            <x-lucide-wifi-off class="size-5 shrink-0 text-primary-400" aria-hidden="true" />
            Hors ligne : vos titres enregistrés restent visibles.
        </p>
    </div>

    <main id="main" {{ $attributes->class('mx-auto w-full max-w-lg px-4 pb-32 pt-6 md:pb-12') }}>
        <x-flash class="mb-6" />
        {{ $slot }}
    </main>

    @if ($items !== [])
        <nav class="fixed inset-x-3 bottom-[max(0.75rem,env(safe-area-inset-bottom))] z-30 rounded-card bg-white shadow-lifted ring-1 ring-slate-200/70 md:hidden" aria-label="Menu principal">
            <ul @class(['mx-auto grid max-w-lg', ['grid-cols-1', 'grid-cols-2', 'grid-cols-3', 'grid-cols-4', 'grid-cols-5'][min(count($items), 5) - 1]])>
                @foreach ($items as $item)
                    <li>
                        <a href="{{ $item['href'] }}" @if ($item['active']) aria-current="page" @endif @class([
                            'flex min-h-16 flex-col items-center justify-center gap-1 text-xs font-medium',
                            'text-slate-950' => $item['active'],
                            'text-slate-500' => ! $item['active'],
                        ])>
                            <span @class(['flex h-8 w-14 items-center justify-center rounded-full transition', 'bg-slate-950 text-white' => $item['active']])>
                                <x-dynamic-component :component="'lucide-'.$item['icon']" class="size-6" aria-hidden="true" />
                            </span>
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif
</x-layouts.base>
