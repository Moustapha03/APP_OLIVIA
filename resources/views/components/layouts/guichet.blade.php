{{--
    Ticket office (/guichet). Tablet / desktop first, large touch tabs.
    The cash register status is always visible in the top bar.

    <x-layouts.guichet title="Vente">
        <x-slot:status><x-badge variant="success" icon="lock-open">Caisse ouverte</x-badge></x-slot:status>
        …
    </x-layouts.guichet>
--}}
@props([
    'title' => null,
    'preview' => false,
])

@php
    $menu = \App\Support\Navigation::for('guichet', auth()->user(), $preview);
    $items = $menu[''] ?? [];
@endphp

<x-layouts.base :title="$title">
    <header class="sticky top-0 z-30 bg-slate-100/90 backdrop-blur">
        <div class="mx-auto flex min-h-16 max-w-6xl flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-2">
            <div class="flex items-center gap-3">
                <x-brand :name="false" />
                <div class="leading-tight">
                    <p class="text-base font-medium text-slate-950">Guichet</p>
                    <p class="text-sm text-slate-500">{{ auth()->user()?->name }}</p>
                </div>
            </div>

            <div class="flex min-w-0 flex-wrap items-center gap-2 sm:gap-3">
                {{ $status ?? '' }}
                @can('space.admin')
                    <a href="{{ route('admin.dashboard') }}" class="inline-flex min-h-11 items-center gap-2 rounded-full bg-white px-4 text-sm font-medium text-slate-700 ring-1 ring-slate-200/70 hover:text-slate-950">
                        <x-lucide-layout-dashboard class="size-5" aria-hidden="true" />
                        <span class="sr-only sm:not-sr-only">Administration</span>
                    </a>
                @endcan
                @if (Route::has('account.password.edit'))
                    <a href="{{ route('account.password.edit') }}" class="inline-flex min-h-11 items-center gap-2 rounded-full bg-white px-4 text-sm font-medium text-slate-700 ring-1 ring-slate-200/70 hover:text-slate-950">
                        <x-lucide-key-round class="size-5" aria-hidden="true" />
                        <span class="sr-only sm:not-sr-only">Mot de passe</span>
                    </a>
                @endif
                <x-logout-button />
            </div>
        </div>

        @if ($items !== [])
            <nav class="mx-auto max-w-6xl px-4" aria-label="Menu du guichet">
                <ul class="flex gap-2 overflow-x-auto pb-3">
                    @foreach ($items as $item)
                        <li>
                            <a href="{{ $item['href'] }}" @if ($item['active']) aria-current="page" @endif @class([
                                'inline-flex min-h-12 items-center gap-2 whitespace-nowrap rounded-full px-5 text-base transition',
                                'bg-slate-950 text-white' => $item['active'],
                                'bg-white text-slate-700 ring-1 ring-slate-200/70 hover:text-slate-950' => ! $item['active'],
                            ])>
                                <x-dynamic-component :component="'lucide-'.$item['icon']" class="size-5" aria-hidden="true" />
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </header>

    <main id="main" {{ $attributes->class('mx-auto w-full max-w-6xl px-4 py-6') }}>
        <x-flash class="mb-6" />
        {{ $slot }}
    </main>
</x-layouts.base>
