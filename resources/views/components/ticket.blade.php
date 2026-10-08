{{--
    Signature component: a transport pass shaped like a real ticket
    (side notches + perforation line between the two halves).

    <x-ticket type="daily" title="Grand-Bassam" status="success" status-label="Valide">
        …details…
        <x-slot:stub>…QR code…</x-slot:stub>
    </x-ticket>

    type: daily (white) | monthly (deep blue, nominative)
    status: badge variant (success | warning | danger | neutral), with status-label.
--}}
@props([
    'type' => 'daily',
    'title',
    'subtitle' => null,
    'status' => null,
    'statusLabel' => null,
])

@php
    $monthly = $type === 'monthly';
    $kind = $monthly ? 'Abonnement mensuel' : 'Ticket journalier';
@endphp

<article {{ $attributes->class('ticket-shadow') }} aria-label="{{ $kind }} – {{ $title }}">
    <div @class([
        'ticket-top rounded-t-3xl px-5 pb-6 pt-5',
        'bg-white text-slate-900' => ! $monthly,
        'bg-linear-to-br from-primary-700 to-primary-900 text-white' => $monthly,
    ])>
        <div class="flex items-center justify-between gap-3">
            <span @class([
                'inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-wide',
                'text-primary-700' => ! $monthly,
                'text-primary-100' => $monthly,
            ])>
                @if ($monthly)
                    <x-lucide-id-card class="size-5" aria-hidden="true" />
                @else
                    <x-lucide-ticket class="size-5" aria-hidden="true" />
                @endif
                {{ $kind }}
            </span>
            @if ($status && $statusLabel)
                <x-badge :variant="$status" class="{{ $monthly ? 'ring-0' : '' }}">{{ $statusLabel }}</x-badge>
            @endif
        </div>

        <p class="mt-3 text-2xl font-semibold tracking-tight">{{ $title }}</p>
        @if ($subtitle)
            <p @class(['mt-0.5 text-base', 'text-slate-600' => ! $monthly, 'text-primary-100' => $monthly])>{{ $subtitle }}</p>
        @endif

        @if ($slot->isNotEmpty())
            <div class="mt-4">{{ $slot }}</div>
        @endif
    </div>

    <div class="ticket-bottom relative rounded-b-3xl bg-white px-5 pb-5 pt-6 text-slate-900">
        <div class="absolute inset-x-6 top-0 border-t-2 border-dashed border-slate-200" aria-hidden="true"></div>
        {{ $stub ?? '' }}
    </div>
</article>
