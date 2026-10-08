{{--
    Button / link button.

    <x-button>Acheter</x-button>
    <x-button href="/espace" variant="secondary" icon="ticket">Mes titres</x-button>
    <x-button type="submit" loading-text="Paiement en cours…">Payer</x-button>

    variant : primary | secondary | success | danger | ghost
    size    : md (48 px, default) | lg (56 px, main action) | sm (40 px, dense admin tables only)
    Submit buttons automatically show a spinner and lock the form (resources/js/forms.js).
--}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
    'icon' => null,
    'iconRight' => null,
    'block' => false,
    'loadingText' => null,
])

@php
    $variants = [
        'primary' => 'bg-primary-600 text-white hover:bg-primary-700 active:bg-primary-800',
        'dark' => 'bg-slate-950 text-white hover:bg-slate-800 active:bg-slate-700',
        'secondary' => 'bg-white text-slate-900 ring-1 ring-inset ring-slate-200 hover:bg-slate-50 active:bg-slate-100',
        'success' => 'bg-success-600 text-white hover:bg-success-700 active:bg-success-800',
        'danger' => 'bg-danger-600 text-white hover:bg-danger-700 active:bg-danger-800',
        'ghost' => 'text-primary-700 hover:bg-primary-50 active:bg-primary-100',
    ];

    $sizes = [
        'sm' => 'min-h-10 gap-1.5 rounded-full px-4 text-sm',
        'md' => 'min-h-12 gap-2 rounded-full px-6 text-base',
        'lg' => 'min-h-14 gap-2.5 rounded-full px-7 text-lg',
    ];

    $iconSize = $size === 'sm' ? 'size-4' : 'size-5';

    $classes = implode(' ', [
        'relative inline-flex select-none items-center justify-center font-medium transition duration-150',
        'disabled:cursor-not-allowed disabled:opacity-60 aria-disabled:cursor-not-allowed aria-disabled:opacity-60',
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
        $block ? 'w-full' : '',
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-dynamic-component :component="'lucide-'.$icon" class="{{ $iconSize }} shrink-0" aria-hidden="true" />@endif
        <span>{{ $slot }}</span>
        @if ($iconRight)<x-dynamic-component :component="'lucide-'.$iconRight" class="{{ $iconSize }} shrink-0" aria-hidden="true" />@endif
    </a>
@else
    <button
        type="{{ $type }}"
        @if ($type === 'submit') data-loading @endif
        @if ($loadingText) data-loading-text="{{ $loadingText }}" @endif
        {{ $attributes->class($classes) }}
    >
        <span data-spinner hidden class="absolute inset-0 flex items-center justify-center">
            <x-lucide-loader-circle class="{{ $iconSize }} animate-spin" aria-hidden="true" />
        </span>
        <span data-label class="inline-flex items-center justify-center gap-[inherit]">
            @if ($icon)<x-dynamic-component :component="'lucide-'.$icon" class="{{ $iconSize }} shrink-0" aria-hidden="true" />@endif
            <span data-text>{{ $slot }}</span>
            @if ($iconRight)<x-dynamic-component :component="'lucide-'.$iconRight" class="{{ $iconSize }} shrink-0" aria-hidden="true" />@endif
        </span>
    </button>
@endif
