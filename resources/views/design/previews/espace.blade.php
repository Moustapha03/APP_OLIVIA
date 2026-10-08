<x-layouts.espace title="Aperçu · Espace client" :preview="true">
    <x-design.preview-banner />

    <div class="space-y-6">
        <div>
            <p class="text-base text-slate-600">Bonjour,</p>
            <h1 class="text-3xl font-bold tracking-tight">Awa</h1>
        </div>

        <x-alert variant="warning" title="Votre abonnement expire dans 3 jours">Renouvelez-le en un clic pour continuer à voyager.</x-alert>

        <x-ticket type="monthly" title="Bonoua" subtitle="Jusqu’au 8 octobre" status="warning" status-label="Expire bientôt">
            <p class="text-sm text-primary-100">Valable jusqu’à Bonoua et les arrêts avant</p>
            <x-slot:stub>
                <div class="flex flex-col items-center gap-4">
                    <x-qr payload="DEMO-ESPACE-NOT-VALID" label="QR code de l’abonnement (démonstration)" />
                    <x-button size="lg" block icon="refresh-cw">Renouveler</x-button>
                    <p class="text-sm text-slate-600">30 jours · <x-money :amount="36000" /></p>
                </div>
            </x-slot:stub>
        </x-ticket>

        <x-button size="lg" variant="secondary" block icon="ticket-plus">Acheter un ticket du jour</x-button>

        <x-card title="Derniers achats" :padding="false">
            <ul class="divide-y divide-slate-100">
                @foreach ([['Abonnement Bonoua', '8 sept.', 36000], ['Ticket Grand-Bassam', '2 sept.', 1500]] as [$label, $date, $amount])
                    <li class="flex items-center justify-between gap-4 px-5 py-3.5">
                        <div>
                            <p class="font-medium">{{ $label }}</p>
                            <p class="text-sm text-slate-600">{{ $date }} · Mobile Money</p>
                        </div>
                        <x-money :amount="$amount" class="font-semibold" />
                    </li>
                @endforeach
            </ul>
        </x-card>
    </div>
</x-layouts.espace>
