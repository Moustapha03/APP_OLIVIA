<x-layouts.public title="Aperçu · Public">
    <x-design.preview-banner />

    <section class="grid items-center gap-8 py-4 md:grid-cols-2 md:py-10">
        <div class="space-y-5">
            <x-badge variant="primary" icon="bus">Abidjan → Bonoua</x-badge>
            <h1 class="text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl">Votre ticket de bus, en une minute.</h1>
            <p class="text-lg text-slate-600">Payez avec Mobile Money, montrez le QR code au contrôleur. C’est tout.</p>
            <div class="flex flex-col gap-3 sm:flex-row">
                <x-button size="lg" icon="ticket-plus">Acheter un ticket</x-button>
                <x-button size="lg" variant="secondary" icon="log-in">Se connecter</x-button>
            </div>
        </div>

        <x-ticket type="daily" title="Grand-Bassam" subtitle="Aujourd’hui, jusqu’à 23 h 59" status="success" status-label="Valide" class="mx-auto w-full max-w-sm rotate-1">
            <p class="text-sm text-slate-600">2 trajets · <x-money :amount="1500" /></p>
            <x-slot:stub>
                <div class="flex justify-center"><x-qr payload="DEMO-PUBLIC-NOT-VALID" :size="140" label="Exemple de QR code" /></div>
            </x-slot:stub>
        </x-ticket>
    </section>

    <section class="mt-10 space-y-4" aria-labelledby="fares-title">
        <h2 id="fares-title" class="text-2xl font-bold tracking-tight">Nos tarifs</h2>
        <p class="text-base text-slate-600">Le prix dépend de votre arrêt, au départ d’Abidjan.</p>
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ($stops as $stop)
                <x-card>
                    <p class="flex items-center gap-2 text-lg font-semibold"><x-lucide-map-pin class="size-5 text-primary-600" aria-hidden="true" />{{ $stop['name'] }}</p>
                    <dl class="mt-4 space-y-3">
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-slate-600">Ticket du jour</dt>
                            <dd class="text-xl font-bold"><x-money :amount="$stop['daily']" /></dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-slate-600">Abonnement 30 jours</dt>
                            <dd class="text-xl font-bold"><x-money :amount="$stop['monthly']" /></dd>
                        </div>
                    </dl>
                </x-card>
            @endforeach
        </div>
    </section>
</x-layouts.public>
