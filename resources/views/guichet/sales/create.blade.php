<x-layouts.guichet title="Ticket du jour">
    <h1 class="sr-only">Vendre un ticket du jour</h1>
    <x-slot:status>
        <x-badge variant="success" icon="lock-open" size="lg">Caisse ouverte</x-badge>
    </x-slot:status>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6">
            @if (session('last_sale'))
                <x-button :href="route('guichet.sales.receipt', ['payment' => session('last_sale'), 'imprimer' => 1])" variant="secondary" block icon="printer">Imprimer le reçu de la dernière vente</x-button>
            @endif

            <form method="POST" action="{{ route('guichet.sales.store') }}" class="space-y-6">
                @csrf

                <x-card title="1. Zone du client" subtitle="Ticket valable aujourd’hui, matin et soir.">
                    @foreach ($lines as $line)
                        <fieldset class="space-y-3">
                            <legend class="sr-only">{{ $line->name }}</legend>
                            <div class="grid gap-3 sm:grid-cols-3">
                                @foreach ($line->zones->sortByDesc('position') as $zone)
                                    @php $fare = $zone->fares->first(); @endphp
                                    @continue(! $fare)
                                    <x-choice
                                        name="zone_id"
                                        :value="$zone->id"
                                        :title="$zone->name"
                                        :description="$zone->stops->pluck('name')->take(4)->join(', ').($zone->stops->count() > 4 ? '…' : '')"
                                        :amount="$fare->amount"
                                        :checked="(string) old('zone_id') === (string) $zone->id"
                                        required
                                    />
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                    @error('zone_id')<x-alert variant="danger" class="mt-4">{{ $message }}</x-alert>@enderror
                    @error('type')<x-alert variant="danger" class="mt-4">{{ $message }}</x-alert>@enderror
                </x-card>

                <x-card title="2. Paiement">
                    <div class="space-y-5">
                        @include('guichet._payment-fields')
                    </div>
                </x-card>

                <div class="grid gap-3 sm:grid-cols-2">
                    <x-button type="submit" variant="success" size="lg" block icon="banknote" loading-text="Encaissement…">Encaisser</x-button>
                    <x-button type="submit" name="print" value="1" variant="secondary" size="lg" block icon="printer" loading-text="Encaissement…">Encaisser et imprimer</x-button>
                </div>
            </form>
        </div>

        @include('guichet._sales-aside')
    </div>
</x-layouts.guichet>
