{{--
    KPI tile for dashboards.

    <x-stat label="Recettes du jour" icon="banknote"><x-money :amount="185000" /></x-stat>
    <x-stat label="Abonnés actifs" value="214" hint="+12 cette semaine" variant="success" icon="users" />
--}}
@props([
    'label',
    'value' => null,
    'hint' => null,
    'icon' => null,
    'variant' => 'primary',
])

@php
    $tones = [
        'primary' => 'bg-primary-600 text-white',
        'success' => 'bg-success-50 text-success-700',
        'warning' => 'bg-warning-50 text-warning-700',
        'danger' => 'bg-danger-50 text-danger-700',
        'neutral' => 'bg-slate-100 text-slate-900',
    ];
@endphp

<div {{ $attributes->class('rounded-card bg-white p-6 shadow-soft ring-1 ring-slate-200/60') }}>
    <div class="flex items-center justify-between gap-3">
        <p class="text-sm text-slate-500">{{ $label }}</p>
        @if ($icon)
            <span class="flex size-11 shrink-0 items-center justify-center rounded-full {{ $tones[$variant] ?? $tones['primary'] }}">
                <x-dynamic-component :component="'lucide-'.$icon" class="size-5" aria-hidden="true" />
            </span>
        @endif
    </div>
    <p class="mt-3 text-3xl font-medium tracking-tight text-slate-950 tabular-nums">{{ $value ?? $slot }}</p>
    @if ($hint)
        <p class="mt-1 text-sm text-slate-500">{{ $hint }}</p>
    @endif
</div>
