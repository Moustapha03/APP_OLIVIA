{{--
    Confirmation before any irreversible action. Submits a real form
    (CSRF + method spoofing), so it works the same with or without JS.

    <x-button variant="danger" data-modal-open="cancel-sale-42">Annuler la vente</x-button>

    <x-confirm name="cancel-sale-42" title="Annuler cette vente ?"
               :action="route('guichet.sales.cancel', $sale)" method="DELETE"
               confirm-label="Oui, annuler la vente">
        Le ticket ne sera plus valable. Cette action est définitive.
        <x-slot:fields><x-textarea name="reason" label="Motif" required /></x-slot:fields>
    </x-confirm>

    variant: danger (default) | warning | primary
--}}
@props([
    'name',
    'title',
    'action',
    'method' => 'POST',
    'confirmLabel' => 'Confirmer',
    'cancelLabel' => 'Non, revenir',
    'variant' => 'danger',
    'show' => false,
])

@php
    $tones = [
        'danger' => ['bg' => 'bg-danger-100 text-danger-700', 'icon' => 'triangle-alert', 'button' => 'danger'],
        'warning' => ['bg' => 'bg-warning-100 text-warning-700', 'icon' => 'triangle-alert', 'button' => 'primary'],
        'primary' => ['bg' => 'bg-primary-100 text-primary-700', 'icon' => 'circle-help', 'button' => 'primary'],
    ];
    $tone = $tones[$variant] ?? $tones['danger'];
    $method = strtoupper($method);
@endphp

<x-modal :name="$name" :show="$show" max-width="md">
    <form method="POST" action="{{ $action }}" class="space-y-5">
        @csrf
        @unless ($method === 'POST')
            @method($method)
        @endunless

        <div class="flex flex-col items-center gap-4 pt-2 text-center">
            <span class="flex size-16 items-center justify-center rounded-full {{ $tone['bg'] }}">
                <x-dynamic-component :component="'lucide-'.$tone['icon']" class="size-8" aria-hidden="true" />
            </span>
            <h2 class="text-xl font-semibold text-slate-900">{{ $title }}</h2>
            @if ($slot->isNotEmpty())
                <div class="text-base text-slate-600">{{ $slot }}</div>
            @endif
        </div>

        @isset($fields)
            <div class="space-y-4 text-left">{{ $fields }}</div>
        @endisset

        <div class="flex flex-col gap-3 pt-1">
            <x-button type="submit" :variant="$tone['button']" size="lg" block>{{ $confirmLabel }}</x-button>
            <x-button variant="secondary" size="lg" block x-on:click="$dispatch('close')">{{ $cancelLabel }}</x-button>
        </div>
    </form>
</x-modal>
