@php
    $usable = $ticket->isUsable();
    [$status, $statusLabel] = match (true) {
        $ticket->status !== App\Enums\PassStatus::Active => ['neutral', $ticket->status->label()],
        $usable => ['success', 'Valide'],
        $ticket->ridesLeft() === 0 => ['neutral', 'Utilisé'],
        default => ['neutral', 'Expiré'],
    };
@endphp

<x-layouts.espace title="Mon ticket">
    <div class="space-y-6">
        <x-page-header title="Mon ticket" :back="route('espace.passes.index')" back-label="Mes titres" />

        <x-ticket
            type="daily"
            :title="$ticket->zone->name"
            :subtitle="($ticket->valid_on->isToday() ? 'Aujourd’hui' : $ticket->valid_on->translatedFormat('l j F Y')).' · vers '.$ticket->zone->line->destination_name"
            :status="$status"
            :status-label="$statusLabel"
        >
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <dt class="text-slate-600">Trajets restants</dt>
                    <dd class="text-lg font-semibold">{{ $ticket->ridesLeft() }} sur {{ $ticket->rides_allowed }}</dd>
                </div>
                <div>
                    <dt class="text-slate-600">Prix payé</dt>
                    <dd class="text-lg font-semibold"><x-money :amount="$ticket->price_paid" /></dd>
                </div>
            </dl>

            <x-slot:stub>
                <div class="flex flex-col items-center gap-3">
                    @if ($usable)
                        <x-qr :payload="$qr" :size="260" label="QR code du ticket, à montrer au contrôleur" />
                        <p class="text-center text-base font-medium text-slate-700">Montrez ce code au contrôleur.<br><span class="text-sm font-normal text-slate-600">Augmentez la luminosité de votre écran.</span></p>
                    @else
                        <p class="py-6 text-center text-base text-slate-600">Ce ticket n’est plus utilisable.</p>
                    @endif
                </div>
            </x-slot:stub>
        </x-ticket>

        @if ($usable)
            <x-button :href="route('espace.tickets.pdf', $ticket->uuid)" variant="secondary" block icon="download">Télécharger (PDF)</x-button>
        @endif

        <p class="text-center text-sm text-slate-500">Acheté le {{ $ticket->created_at->format('d/m/Y à H\hi') }} · {{ $ticket->channel->label() }}</p>
    </div>
</x-layouts.espace>
