@php $editing = $expense->exists; @endphp

<x-layouts.admin :title="$editing ? 'Modifier la dépense' : 'Nouvelle dépense'">
    <div class="max-w-2xl space-y-6">
        <x-page-header :title="$editing ? 'Modifier la dépense' : 'Nouvelle dépense'" :back="route('admin.expenses.index')" back-label="Dépenses" />

        <x-card>
            <form method="POST" action="{{ $editing ? route('admin.expenses.update', $expense) : route('admin.expenses.store') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf
                @if ($editing)
                    @method('PUT')
                @endif

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-input name="spent_on" label="Date" type="date" :value="$expense->spent_on?->format('Y-m-d')" :max="today()->format('Y-m-d')" required />
                    <x-select name="expense_category_id" label="Type de dépense" :options="$categoryOptions" :value="$expense->expense_category_id" placeholder="Choisissez le type" required />
                </div>

                <x-input name="label" label="Libellé" :value="$expense->label" placeholder="Ex. : gasoil Bus 12, vidange, salaire chauffeur…" maxlength="200" required />

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-input name="amount" label="Montant" inputmode="numeric" pattern="[0-9 .]+" suffix="FCFA" :value="$expense->amount" required />
                    <x-select name="payment_method" label="Payé par" :options="collect($methods)->mapWithKeys(fn ($m) => [$m->value => $m->label()])->all()" :value="$expense->payment_method?->value" required />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-input name="reference" label="Référence" :value="$expense->reference" hint="N° de facture, de transfert ou de chèque." />
                    <x-select name="vehicle_id" label="Véhicule concerné" :options="$vehicleOptions" :value="$expense->vehicle_id" />
                </div>

                <x-textarea name="notes" label="Remarques" :value="$expense->notes" rows="2" />

                <x-field id="attachment" :label="$expense->hasAttachment() ? 'Remplacer le justificatif' : 'Justificatif (photo ou PDF)'" :error="$errors->first('attachment')" hint="Facture, reçu… JPG, PNG, WEBP ou PDF, 5 Mo maximum." :required="false">
                    <input id="attachment" name="attachment" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" aria-describedby="attachment-hint attachment-error"
                        class="block w-full rounded-full bg-white text-base text-slate-700 ring-1 ring-inset ring-slate-200 file:mr-4 file:min-h-12 file:cursor-pointer file:rounded-full file:border-0 file:bg-slate-950 file:px-5 file:font-medium file:text-white">
                </x-field>
                @if ($expense->hasAttachment())
                    <div class="flex flex-wrap items-center gap-4 text-sm">
                        <a href="{{ route('admin.expenses.attachment', $expense) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 font-medium text-primary-700 hover:underline">
                            <x-lucide-paperclip class="size-4" aria-hidden="true" />Voir le justificatif actuel
                        </a>
                        <x-checkbox name="remove_attachment">Retirer le justificatif</x-checkbox>
                    </div>
                @endif

                <x-button type="submit" size="lg" block icon="save" loading-text="Enregistrement…">{{ $editing ? 'Enregistrer' : 'Enregistrer la dépense' }}</x-button>
            </form>
        </x-card>

        @if ($editing)
            @can('delete', $expense)
                <x-delete-card name="delete-expense" :action="route('admin.expenses.destroy', $expense)" label="Supprimer la dépense" title="Supprimer cette dépense ?">
                    Elle ne comptera plus dans les totaux. La suppression reste inscrite au journal d’activité.
                </x-delete-card>
            @endcan
        @endif
    </div>
</x-layouts.admin>
