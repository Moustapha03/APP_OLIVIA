<x-layouts.admin title="Programmer un tarif">
    <div class="max-w-2xl space-y-6">
        <x-page-header title="Programmer un nouveau prix" :back="route('admin.fares.index')" />

        <x-alert variant="info" title="Comment ça marche">
            Le nouveau prix s’applique aux achats faits <strong>à partir de la date choisie</strong>. L’ancien prix s’arrête la veille.
            Les titres déjà vendus et les abonnements en cours ne changent pas.
        </x-alert>

        <x-card>
            <form method="POST" action="{{ route('admin.fares.store') }}" class="space-y-6">
                @csrf

                <x-select name="zone_id" label="Zone" :options="$zoneOptions" :value="$selectedZone" placeholder="Choisissez une zone" required />

                <fieldset class="space-y-3">
                    <legend class="mb-3 text-base font-medium text-slate-800">Formule</legend>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($types as $type)
                            <x-choice
                                name="type"
                                :value="$type->value"
                                :title="$type->shortLabel()"
                                :description="$type === App\Enums\FareType::Daily ? 'Prix d’un jour' : 'Prix du mois'"
                                :icon="$type === App\Enums\FareType::Daily ? 'ticket' : 'id-card'"
                                :checked="$selectedType === $type->value"
                                required
                            />
                        @endforeach
                    </div>
                </fieldset>

                <x-input
                    name="amount"
                    label="Nouveau prix"
                    inputmode="numeric"
                    pattern="[0-9 ]+"
                    data-pattern-message="Uniquement des chiffres, sans centimes."
                    suffix="FCFA"
                    placeholder="1500"
                    hint="Nombre entier, sans centimes."
                    required
                />

                <x-input
                    name="effective_from"
                    label="À partir du"
                    type="date"
                    :value="today()->toDateString()"
                    :min="today()->toDateString()"
                    hint="Aujourd’hui ou une date future. Jamais dans le passé."
                    required
                />

                <x-button type="submit" size="lg" block icon="calendar-check" loading-text="Enregistrement…">Programmer ce prix</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.admin>
