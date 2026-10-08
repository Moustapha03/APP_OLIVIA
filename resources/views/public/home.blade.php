<x-layouts.public title="Accueil">
    <section class="grid items-center gap-8 py-4 md:grid-cols-2 md:py-10">
        <div class="space-y-5">
            <x-badge variant="primary" icon="bus">Navette vers Angré · matin et soir</x-badge>
            <h1 class="text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl">Votre abonnement de bus, sans file d’attente.</h1>
            <p class="text-lg text-slate-600">Abonnement au mois ou ticket du jour. Montrez votre QR code au contrôleur, c’est tout.</p>

            @guest
                <div class="flex flex-col gap-3 sm:flex-row">
                    <x-button :href="route('login')" size="lg" icon="log-in">Se connecter</x-button>
                    <x-button :href="route('register')" size="lg" variant="secondary" icon="user-plus">Créer mon compte</x-button>
                </div>
            @else
                <x-button :href="route('dashboard')" size="lg" icon="arrow-right">Aller à mon espace</x-button>
            @endguest
        </div>

        <ol class="space-y-3">
            @foreach ([
                ['map-pin', 'Votre point de montée', 'Le prix dépend de votre zone.'],
                ['calendar', 'Abonnement du mois', 'Matin & soir, matin seul ou soir seul. Ou un ticket du jour.'],
                ['smartphone', 'Payez à l’agence ou par Wave', 'Espèces ou Mobile Money.'],
                ['qr-code', 'Montrez votre QR code', 'Le contrôleur le scanne dans le bus.'],
            ] as $index => [$icon, $title, $text])
                <li class="flex gap-4 rounded-2xl bg-white p-4 shadow-soft ring-1 ring-slate-200/70">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
                        <x-dynamic-component :component="'lucide-'.$icon" class="size-6" aria-hidden="true" />
                    </span>
                    <div>
                        <p class="font-semibold text-slate-900"><span class="sr-only">Étape {{ $index + 1 }} : </span>{{ $title }}</p>
                        <p class="text-base text-slate-600">{{ $text }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    @foreach ($grid as $row)
        <section class="mt-12 space-y-4" aria-labelledby="fares-{{ $row['line']->id }}">
            <div>
                <h2 id="fares-{{ $row['line']->id }}" class="text-2xl font-semibold tracking-tight">Nos tarifs vers {{ $row['line']->destination_name }}</h2>
                <p class="text-base text-slate-600">Le prix dépend de votre zone de montée.</p>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                @foreach ($row['zones'] as $zoneRow)
                    <x-card :title="$zoneRow['zone']->name" :subtitle="implode(' · ', $zoneRow['stops'])">
                        <dl class="space-y-2">
                            @foreach ($types as $type)
                                @isset($zoneRow['prices'][$type->value])
                                    <div class="flex items-baseline justify-between gap-2">
                                        <dt class="text-slate-600">{{ $type->shortLabel() }}{{ $type->isSubscription() ? ' (mois)' : '' }}</dt>
                                        <dd class="text-lg font-semibold"><x-money :amount="$zoneRow['prices'][$type->value]" /></dd>
                                    </div>
                                @endisset
                            @endforeach
                        </dl>
                    </x-card>
                @endforeach
            </div>
        </section>
    @endforeach
</x-layouts.public>
