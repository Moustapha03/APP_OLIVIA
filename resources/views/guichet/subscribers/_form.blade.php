{{-- Subscriber form. $action, $model (?Subscriber), $then (?string), $stopOptions, $accountForPhone (?User) --}}
@php $model ??= null; @endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-5">
    @csrf
    @if ($model)
        @method('PUT')
    @endif
    @if (! empty($then))
        <input type="hidden" name="then" value="{{ $then }}">
    @endif

    <x-input name="full_name" label="Nom et prénom" :value="$model?->full_name" autocomplete="off" placeholder="Ex. : KOUASSI Rodolphe" required />
    <x-input
        name="phone"
        label="Téléphone"
        type="tel"
        prefix="+225"
        inputmode="numeric"
        :value="$model?->phone ? $model->formatted_phone : ($prefillPhone ?? null)"
        hint="Peut être le numéro de la personne qui paie (plusieurs abonnés possibles)."
    />
    <x-select name="stop_id" label="Point de montée" :options="$stopOptions" :value="$model?->stop_id" placeholder="Choisissez le point de montée" required />

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
        @if ($model?->hasPhoto())
            <img src="{{ route('photos.subscriber', $model) }}" alt="Photo actuelle" class="size-24 shrink-0 rounded-2xl object-cover ring-1 ring-slate-200">
        @endif
        <div class="flex-1">
            <x-field id="photo" :label="$model?->hasPhoto() ? 'Changer la photo' : 'Photo du visage'" :error="$errors->first('photo')" hint="Obligatoire pour vendre un abonnement. De face, bien éclairée." :required="false">
                <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" capture="user" data-validate aria-describedby="photo-hint photo-error"
                    class="block w-full rounded-2xl bg-white text-base text-slate-700 ring-1 ring-inset ring-slate-300 file:mr-4 file:min-h-12 file:cursor-pointer file:border-0 file:bg-primary-50 file:px-4 file:font-semibold file:text-primary-700">
            </x-field>
        </div>
    </div>

    <x-textarea name="notes" label="Remarques" :value="$model?->notes" rows="2" />

    <x-checkbox name="link_account" :checked="$model?->user_id !== null">
        Rattacher au compte client de ce numéro (s’il existe) : ce compte verra les QR codes de cet abonné.
        <span class="block text-sm text-slate-500">Seulement après avoir vérifié la pièce d’identité de la personne qui a ce compte : n’importe qui peut créer un compte avec un numéro.</span>
    </x-checkbox>
    @if ($model && isset($accountForPhone))
        <p class="-mt-3 text-sm text-slate-600">
            @if ($accountForPhone)
                Compte client existant pour ce numéro : {{ $accountForPhone->name }}{{ $model->user_id === $accountForPhone->id ? ' (rattaché)' : '' }}.
            @else
                Aucun compte client avec ce numéro.
            @endif
        </p>
    @endif

    <x-button type="submit" size="lg" block icon="save" loading-text="Enregistrement…">{{ $submitLabel ?? 'Enregistrer l’abonné' }}</x-button>
</form>
