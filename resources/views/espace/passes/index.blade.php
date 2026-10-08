<x-layouts.espace title="Mes titres">
    <div class="space-y-8">
        <x-page-header title="Mes titres">
            @can('passes.purchase')
                <x-slot:actions>
                    <x-button :href="route('espace.subscribers.index')" variant="secondary" icon="users">Mes abonnés</x-button>
                    <x-button :href="route('espace.purchase.create')" icon="ticket-plus">Acheter</x-button>
                </x-slot:actions>
            @endcan
        </x-page-header>

        @if ($todayTickets->isEmpty() && $currentSubscriptions->isEmpty())
            <x-empty-state icon="ticket" title="Aucun titre valable en ce moment">
                Achetez un ticket du jour ou un abonnement en moins d’une minute.
                <x-slot:action><x-button :href="route('espace.purchase.create')" size="lg" block icon="ticket-plus">Acheter un titre</x-button></x-slot:action>
            </x-empty-state>
        @else
            <section class="space-y-3" aria-labelledby="current-title">
                <h2 id="current-title" class="text-lg font-semibold">À utiliser</h2>
                @foreach ($currentSubscriptions as $pass)
                    @include('espace.passes._summary', ['pass' => $pass])
                @endforeach
                @foreach ($todayTickets as $pass)
                    @include('espace.passes._summary', ['pass' => $pass])
                @endforeach
            </section>
        @endif

        @if ($history->isNotEmpty())
            <section class="space-y-3" aria-labelledby="history-title">
                <h2 id="history-title" class="text-lg font-semibold">Historique</h2>
                @foreach ($history as $pass)
                    @include('espace.passes._summary', ['pass' => $pass])
                @endforeach
            </section>
        @endif
    </div>
</x-layouts.espace>
