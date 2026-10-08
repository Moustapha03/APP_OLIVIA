{{--
    On-board controller app (/controle). Dark, full height, phone only:
    dark background saves battery and reduces glare inside the bus.
    The network badge tells the controller when scans are stored offline.

    <x-layouts.controle title="Scanner">
        <x-slot:trip>Bus 12 · départ 07:30</x-slot:trip>
        …
    </x-layouts.controle>
--}}
@props([
    'title' => null,
    'preview' => false,
])

@php
    $menu = \App\Support\Navigation::for('controle', auth()->user(), $preview);
    $items = $menu[''] ?? [];
@endphp

<x-layouts.base :title="$title" body-class="bg-slate-950 text-white" theme-color="#141414">
    <div class="flex min-h-dvh flex-col">
        <header class="sticky top-0 z-30 border-b border-white/10 bg-slate-900/95 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-lg items-center justify-between gap-3 px-4">
                <div class="flex min-w-0 items-center gap-3">
                    <x-brand :name="false" />
                    <div class="min-w-0 leading-tight">
                        <p class="text-base font-bold">Contrôle</p>
                        <p class="truncate text-sm text-slate-300">{{ $trip ?? 'Aucun voyage en cours' }}</p>
                    </div>
                </div>

                <span x-data="networkStatus" class="inline-flex shrink-0 items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-sm font-semibold" role="status">
                    <span class="size-2.5 rounded-full" x-bind:class="online ? 'bg-success-500' : 'bg-warning-400 animate-pulse'" aria-hidden="true"></span>
                    <span x-text="online ? 'En ligne' : 'Hors ligne'">En ligne</span>
                </span>
            </div>
        </header>

        <main id="main" {{ $attributes->class('mx-auto flex w-full max-w-lg flex-1 flex-col px-4 pb-28 pt-6') }}>
            <x-flash class="mb-6" />
            {{ $slot }}
        </main>

        @if ($items !== [])
            <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-white/10 bg-slate-900 pb-[env(safe-area-inset-bottom)]" aria-label="Menu du contrôle">
                <ul @class(['mx-auto grid max-w-lg', ['grid-cols-1', 'grid-cols-2', 'grid-cols-3', 'grid-cols-4'][min(count($items), 4) - 1]])>
                    @foreach ($items as $item)
                        <li>
                            <a href="{{ $item['href'] }}" @if ($item['active']) aria-current="page" @endif @class([
                                'flex min-h-16 flex-col items-center justify-center gap-1 text-sm font-medium',
                                'text-white' => $item['active'],
                                'text-slate-400 hover:text-slate-200' => ! $item['active'],
                            ])>
                                <span @class(['flex h-8 w-14 items-center justify-center rounded-full', 'bg-primary-600' => $item['active']])>
                                    <x-dynamic-component :component="'lucide-'.$item['icon']" class="size-6" aria-hidden="true" />
                                </span>
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </div>
</x-layouts.base>
