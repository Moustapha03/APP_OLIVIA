<x-layouts.espace title="Accueil">
    <div class="space-y-6">
        <div>
            <p class="text-base text-slate-600">Bonjour,</p>
            <h1 class="text-3xl font-semibold tracking-tight">{{ auth()->user()->first_name }}</h1>
        </div>

        @if ($expiring)
            <x-alert variant="warning" title="L’abonnement de {{ $expiring->subscriber->full_name }} se termine le {{ $expiring->ends_on->translatedFormat('j F') }}">
                <a href="{{ route('espace.subscriptions.show', $expiring->uuid) }}" class="font-semibold underline underline-offset-4">Acheter le mois suivant</a>
            </x-alert>
        @endif

        @if ($passes->isEmpty())
            <x-empty-state icon="ticket" title="Vous n’avez pas de titre valable">
                Achetez un ticket du jour ou un abonnement en moins d’une minute.
                @can('passes.purchase')
                    <x-slot:action><x-button :href="route('espace.purchase.create')" size="lg" block icon="ticket-plus">Acheter un titre</x-button></x-slot:action>
                @endcan
            </x-empty-state>
        @else
            <section class="space-y-3" aria-labelledby="passes-title">
                <h2 id="passes-title" class="text-lg font-semibold">Mes titres valables</h2>
                @foreach ($passes as $pass)
                    @include('espace.passes._summary', ['pass' => $pass])
                @endforeach
                <p class="text-sm text-slate-600">Touchez un titre pour afficher son QR code.</p>
            </section>

            @can('passes.purchase')
                <x-button :href="route('espace.purchase.create')" variant="secondary" size="lg" block icon="ticket-plus">Acheter un autre titre</x-button>
            @endcan
        @endif

        <x-install-app />
    </div>
</x-layouts.espace>
