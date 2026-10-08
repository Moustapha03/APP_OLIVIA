{{--
    <x-badge variant="success" icon="circle-check">Valide</x-badge>

    variant: neutral | primary | success | danger | warning
    Always pair colour with a word: colour alone is never the only signal.
--}}
@props([
    'variant' => 'neutral',
    'icon' => null,
    'size' => 'md',
])

@php
    $variants = [
        'neutral' => 'bg-slate-100 text-slate-700 ring-slate-200',
        'primary' => 'bg-primary-50 text-primary-700 ring-primary-200',
        'success' => 'bg-success-50 text-success-800 ring-success-100',
        'danger' => 'bg-danger-50 text-danger-700 ring-danger-100',
        'warning' => 'bg-warning-50 text-warning-800 ring-warning-100',
    ];
    $sizes = [
        'md' => 'gap-1.5 px-2.5 py-1 text-sm',
        'lg' => 'gap-2 px-3.5 py-1.5 text-base',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center whitespace-nowrap rounded-full font-semibold ring-1 ring-inset',
    $variants[$variant] ?? $variants['neutral'],
    $sizes[$size] ?? $sizes['md'],
]) }}>
    @if ($icon)
        <x-dynamic-component :component="'lucide-'.$icon" class="{{ $size === 'lg' ? 'size-5' : 'size-4' }} shrink-0" aria-hidden="true" />
    @endif
    {{ $slot }}
</span>
