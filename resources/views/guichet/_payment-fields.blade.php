{{-- Payment fields of a desk sale. $methods: list<PaymentMethod> --}}
<fieldset class="space-y-3">
    <legend class="mb-3 text-base font-medium text-slate-800">Paiement</legend>
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
        @foreach ($methods as $method)
            <label class="flex min-h-12 cursor-pointer items-center justify-center rounded-2xl bg-white px-3 text-center text-sm font-semibold ring-1 ring-inset ring-slate-300 transition hover:bg-slate-50 has-checked:bg-primary-50 has-checked:text-primary-800 has-checked:ring-2 has-checked:ring-primary-600">
                <input type="radio" name="payment_method" value="{{ $method->value }}" class="sr-only" @checked(old('payment_method', 'cash') === $method->value) required>
                {{ $method->label() }}
            </label>
        @endforeach
    </div>
    @error('payment_method')
        <p class="text-sm font-medium text-danger-700">{{ $message }}</p>
    @enderror
</fieldset>

<x-input
    name="external_reference"
    label="Référence du transfert"
    hint="Obligatoire pour Wave, Orange Money, MTN ou Moov : le numéro de transaction reçu par SMS."
    placeholder="Ex. : T_4AB7C2…"
    autocomplete="off"
/>

{{-- Price below the grid: reason always; validated on the spot by a manager when the agent may not grant it. --}}
<details @if (old('amount') || $errors->hasAny(['amount', 'discount_reason', 'approver_login'])) open @endif class="rounded-tile bg-slate-50 p-4 ring-1 ring-inset ring-slate-200">
    <summary class="cursor-pointer text-base font-medium text-slate-800">Prix négocié (différent de la grille) ?</summary>
    <div class="mt-4 space-y-4">
        <x-input name="amount" label="Montant encaissé" inputmode="numeric" pattern="[0-9 .]+" suffix="FCFA" hint="Laissez vide pour appliquer le prix de la grille." />
        <x-textarea name="discount_reason" label="Motif" rows="2" placeholder="Ex. : tarif négocié avec la direction, client fidèle…" hint="Obligatoire si le montant est différent. Le motif est enregistré." />
        @cannot('sales.discount')
            <div class="space-y-4 rounded-tile bg-white p-4 ring-1 ring-inset ring-slate-200">
                <p class="text-sm text-slate-600">Un responsable valide ce prix ici, avec son téléphone et son mot de passe.</p>
                <x-input name="approver_login" label="Téléphone du responsable" type="tel" inputmode="tel" autocomplete="off" />
                <x-input name="approver_password" label="Mot de passe du responsable" type="password" autocomplete="off" />
            </div>
        @endcannot
    </div>
</details>
