{{--
    Back office (/admin). Sidebar from 1024 px, slide-in drawer below.

    <x-layouts.admin title="Tarifs">…</x-layouts.admin>
--}}
@props([
    'title' => null,
    'preview' => false,
])

@php
    $menu = \App\Support\Navigation::for('admin', auth()->user(), $preview);
@endphp

<x-layouts.base :title="$title" x-data="drawer">
    {{-- Mobile top bar --}}
    <header class="sticky top-0 z-30 flex h-18 items-center justify-between gap-3 bg-slate-100/90 px-4 backdrop-blur lg:hidden">
        <x-brand />
        <button type="button" x-on:click="toggle" class="inline-flex min-h-11 items-center gap-2 rounded-full bg-white px-4 text-base font-medium text-slate-900 ring-1 ring-slate-200 hover:bg-slate-50" aria-controls="admin-sidebar" x-bind:aria-expanded="open ? 'true' : 'false'">
            <x-lucide-menu class="size-6" aria-hidden="true" />
            Menu
        </button>
    </header>

    {{-- Drawer backdrop (mobile) --}}
    <div x-show="open" x-cloak x-on:click="close" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden" aria-hidden="true"></div>

    <aside
        id="admin-sidebar"
        x-bind:class="open ? 'translate-x-0' : '-translate-x-full'"
        x-on:keydown.escape.window="close"
        class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-slate-100 transition-transform duration-200 lg:translate-x-0"
    >
        <div class="flex h-20 shrink-0 items-center justify-between px-6">
            <x-brand />
            <button type="button" x-on:click="close" class="flex size-11 items-center justify-center rounded-full bg-white text-slate-700 ring-1 ring-slate-200 lg:hidden" aria-label="Fermer le menu">
                <x-lucide-x class="size-6" aria-hidden="true" />
            </button>
        </div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-4 py-4" aria-label="Menu d'administration">
            @foreach ($menu as $section => $items)
                <div>
                    @if ($section !== '')
                        <p class="px-4 pb-2 text-sm text-slate-400">{{ $section }}</p>
                    @endif
                    <ul class="space-y-1">
                        @foreach ($items as $item)
                            <li>
                                <a href="{{ $item['href'] }}" @if ($item['active']) aria-current="page" @endif @class([
                                    'flex min-h-11 items-center gap-3 rounded-full px-4 text-base transition',
                                    'bg-slate-950 text-white' => $item['active'],
                                    'text-slate-700 hover:bg-white' => ! $item['active'],
                                ])>
                                    <x-dynamic-component :component="'lucide-'.$item['icon']" @class(['size-5', 'text-primary-400' => $item['active'], 'text-slate-500' => ! $item['active']]) aria-hidden="true" />
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        <div class="m-4 rounded-card bg-white p-3 ring-1 ring-slate-200/60">
            <div class="flex items-center gap-3 px-3 py-2">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-700">
                    <x-lucide-user-round class="size-5" aria-hidden="true" />
                </span>
                <div class="min-w-0 leading-tight">
                    <p class="truncate text-base font-medium text-slate-950">{{ auth()->user()?->name }}</p>
                    <p class="truncate text-sm text-slate-500">{{ auth()->user()?->formatted_phone }}</p>
                </div>
            </div>
            @if (Route::has('account.password.edit'))
                <a href="{{ route('account.password.edit') }}" class="mt-1 flex min-h-11 items-center gap-2 rounded-full px-3 text-sm font-medium text-slate-700 hover:bg-slate-100">
                    <x-lucide-key-round class="size-5" aria-hidden="true" />
                    Changer mon mot de passe
                </a>
            @endif
            <x-logout-button />
        </div>
    </aside>

    <div class="lg:pl-72">
        <main id="main" {{ $attributes->class('mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8') }}>
            <x-flash class="mb-6" />
            {{ $slot }}
        </main>
    </div>
</x-layouts.base>
