{{--
    Result of a pass scan, readable at arm's length.
    Full screen on the controller app; framed when previewed on /design.

    <x-scan-result status="valid" reason="Abonnement · Grand-Bassam" holder="Awa Koné" :photo="$url" fullscreen>
        <x-slot:action><x-button …>Scanner le suivant</x-button></x-slot:action>
    </x-scan-result>

    status: valid | refused | warning
--}}
@props([
    'status' => 'valid',
    'reason' => null,
    'holder' => null,
    'photo' => null,
    'fullscreen' => false,
])

@php
    $styles = [
        'valid' => ['bg' => 'bg-success-600 text-white', 'icon' => 'circle-check', 'label' => 'VALIDE'],
        'refused' => ['bg' => 'bg-danger-600 text-white', 'icon' => 'circle-x', 'label' => 'REFUSÉ'],
        'warning' => ['bg' => 'bg-warning-400 text-slate-950', 'icon' => 'triangle-alert', 'label' => 'ATTENTION'],
    ];
    $style = $styles[$status] ?? $styles['refused'];
@endphp

<div
    role="status"
    aria-live="assertive"
    data-scan-status="{{ $status }}"
    {{ $attributes->class([
        '@container flex min-w-0 flex-col items-center justify-center-safe gap-6 px-6 py-10 text-center',
        $style['bg'],
        // Small phones: scroll instead of cutting the top ("safe" centring).
        'fixed inset-0 z-50 overflow-y-auto' => $fullscreen,
        'rounded-3xl' => ! $fullscreen,
    ]) }}
>
    <x-dynamic-component :component="'lucide-'.$style['icon']" class="size-24 animate-pop sm:size-32" stroke-width="2.5" aria-hidden="true" />

    {{-- Size follows the frame width (container query): "ATTENTION" fits a 360 px phone. --}}
    <p class="text-[clamp(2.25rem,14cqw,5rem)] leading-none font-black tracking-tight">{{ $style['label'] }}</p>

    @if ($reason)
        <p class="max-w-md text-xl font-semibold sm:text-2xl">{{ $reason }}</p>
    @endif

    @if ($photo || $holder)
        <div class="flex flex-col items-center gap-3">
            @if ($photo)
                <img src="{{ $photo }}" alt="Photo de {{ $holder ?? 'l’abonné' }}" class="size-40 rounded-3xl object-cover ring-4 ring-white/80">
            @endif
            @if ($holder)
                <p class="text-xl font-bold">{{ $holder }}</p>
            @endif
        </div>
    @endif

    @if ($slot->isNotEmpty())
        <div class="text-lg">{{ $slot }}</div>
    @endif

    @isset($action)
        <div class="w-full max-w-sm pt-2">{{ $action }}</div>
    @endisset
</div>
