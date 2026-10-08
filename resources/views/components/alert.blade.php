{{--
    <x-alert variant="success" title="Paiement reçu">Votre ticket est prêt.</x-alert>

    variant: info | success | warning | danger
    dismissible: adds a "Fermer" button.
--}}
@props([
    'variant' => 'info',
    'title' => null,
    'dismissible' => false,
])

@php
    $styles = [
        'info' => ['box' => 'bg-primary-50 ring-primary-200 text-primary-900', 'icon' => 'info', 'iconColor' => 'text-primary-600'],
        'success' => ['box' => 'bg-success-50 ring-success-100 text-success-800', 'icon' => 'circle-check', 'iconColor' => 'text-success-600'],
        'warning' => ['box' => 'bg-warning-50 ring-warning-100 text-warning-800', 'icon' => 'triangle-alert', 'iconColor' => 'text-warning-600'],
        'danger' => ['box' => 'bg-danger-50 ring-danger-100 text-danger-800', 'icon' => 'circle-x', 'iconColor' => 'text-danger-600'],
    ];
    $style = $styles[$variant] ?? $styles['info'];
    $urgent = in_array($variant, ['warning', 'danger'], true);
@endphp

<div
    @if ($dismissible) x-data="dismissible" x-show="open" x-transition.opacity.duration.200ms @endif
    role="{{ $urgent ? 'alert' : 'status' }}"
    {{ $attributes->class(['flex gap-3 rounded-tile p-4 ring-1 ring-inset', $style['box']]) }}
>
    <x-dynamic-component :component="'lucide-'.$style['icon']" class="mt-0.5 size-6 shrink-0 {{ $style['iconColor'] }}" aria-hidden="true" />

    <div class="min-w-0 flex-1 text-base">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        @if ($slot->isNotEmpty())
            <div @class(['mt-0.5' => $title])>{{ $slot }}</div>
        @endif
    </div>

    @if ($dismissible)
        <button type="button" x-on:click="close" class="-m-1.5 flex size-10 shrink-0 items-center justify-center rounded-full hover:bg-black/5" aria-label="Fermer ce message">
            <x-lucide-x class="size-5" aria-hidden="true" />
        </button>
    @endif
</div>
