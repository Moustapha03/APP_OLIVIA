{{-- One line per pass in a list. $pass: Ticket|Subscription --}}
@php
    $isSubscription = $pass instanceof App\Models\Subscription;
    $url = $isSubscription ? route('espace.subscriptions.show', $pass->uuid) : route('espace.tickets.show', $pass->uuid);

    if ($isSubscription) {
        [$variant, $label] = match (true) {
            $pass->status !== App\Enums\PassStatus::Active => ['neutral', $pass->status->label()],
            $pass->isExpired() => ['neutral', 'Terminé'],
            ! $pass->hasStarted() => ['primary', 'À partir du 1er'],
            $pass->expiresSoon((int) config('transport.subscriptions.reminder_days_before', 3)) => ['warning', 'Fin dans '.$pass->daysLeft().' j'],
            default => ['success', 'Valide'],
        };
        $title = $pass->subscriber->full_name;
        $detail = ucfirst($pass->monthLabel()).' · '.$pass->formula->shortLabel().' · '.($pass->stop?->name ?? $pass->zone->name);
    } else {
        [$variant, $label] = match (true) {
            $pass->status !== App\Enums\PassStatus::Active => ['neutral', $pass->status->label()],
            $pass->isUsable() => ['success', 'Valide'],
            $pass->ridesLeft() === 0 => ['neutral', 'Utilisé'],
            default => ['neutral', 'Expiré'],
        };
        $title = 'Ticket du jour · '.$pass->zone->name;
        $detail = $pass->valid_on->translatedFormat('l j F Y').' · '.$pass->ridesLeft().' trajet(s) restant(s)';
    }
@endphp

{{-- Valid and coming passes are kept on the phone for the bus (resources/js/pwa.js). --}}
<a href="{{ $url }}" @if (in_array($variant, ['success', 'primary', 'warning'], true)) data-offline-page @endif class="flex items-center gap-4 rounded-tile bg-white p-4 shadow-soft ring-1 ring-slate-200/60 transition hover:ring-slate-300">
    <span @class([
        'flex size-12 shrink-0 items-center justify-center rounded-full',
        'bg-primary-700 text-white' => $isSubscription,
        'bg-primary-50 text-primary-700' => ! $isSubscription,
    ])>
        @if ($isSubscription)
            <x-lucide-id-card class="size-6" aria-hidden="true" />
        @else
            <x-lucide-ticket class="size-6" aria-hidden="true" />
        @endif
    </span>
    <span class="min-w-0 flex-1">
        <span class="block truncate font-semibold text-slate-900">{{ $title }}</span>
        <span class="block truncate text-sm text-slate-600">{{ $detail }}</span>
    </span>
    <x-badge :variant="$variant">{{ $label }}</x-badge>
</a>
