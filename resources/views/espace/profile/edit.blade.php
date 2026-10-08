<x-layouts.espace title="Mon profil">
    <div class="space-y-6">
        <x-page-header title="Mon profil" />

        <x-card title="Mes informations">
            <form method="POST" action="{{ route('espace.profile.update') }}" class="space-y-5">
                @csrf
                @method('PATCH')

                <x-input name="first_name" label="Prénom" :value="$user->first_name" autocomplete="given-name" required />
                <x-input name="last_name" label="Nom" :value="$user->last_name" autocomplete="family-name" required />

                <div class="space-y-1.5">
                    <p class="text-base font-medium text-slate-800">Numéro de téléphone</p>
                    <p class="flex min-h-12 items-center rounded-2xl bg-slate-50 px-3.5 text-base text-slate-700 ring-1 ring-inset ring-slate-200">+225 {{ $user->formatted_phone }}</p>
                    <p class="text-sm text-slate-600">Pour changer de numéro, passez au guichet avec votre pièce d’identité.</p>
                </div>

                <div class="space-y-2">
                    <x-input
                        name="email"
                        label="Adresse e-mail"
                        type="email"
                        :value="$user->email"
                        autocomplete="email"
                        hint="Elle sert uniquement à recevoir un lien si vous oubliez votre mot de passe."
                    />
                    @if ($user->email)
                        @if ($user->hasVerifiedEmail())
                            <x-badge variant="success" icon="circle-check">Adresse confirmée</x-badge>
                        @else
                            <x-badge variant="warning" icon="clock">Adresse pas encore confirmée</x-badge>
                        @endif
                    @endif
                </div>

                <x-button type="submit" block>Enregistrer</x-button>
            </form>

            @if ($user->email && ! $user->hasVerifiedEmail())
                <form method="POST" action="{{ route('verification.send') }}" class="mt-3">
                    @csrf
                    <x-button type="submit" variant="ghost" block icon="send">Renvoyer le lien de confirmation</x-button>
                </form>
            @endif
        </x-card>

        <x-card title="Mes abonnés" subtitle="Les personnes pour qui vous achetez un abonnement, avec leur photo.">
            <x-button :href="route('espace.subscribers.index')" variant="secondary" block icon="users">Gérer mes abonnés</x-button>
        </x-card>

        <x-card title="Mot de passe">
            <x-button :href="route('account.password.edit')" variant="secondary" block icon="key-round">Changer mon mot de passe</x-button>
        </x-card>

        <x-card title="Session">
            <x-logout-button />
        </x-card>

        <x-card title="Supprimer mon compte" subtitle="Vos informations personnelles seront effacées. Cette action est définitive.">
            <x-button variant="danger" block icon="trash-2" data-modal-open="delete-account">Supprimer mon compte</x-button>
        </x-card>
    </div>

    <x-confirm
        name="delete-account"
        title="Supprimer votre compte ?"
        :action="route('espace.profile.destroy')"
        method="DELETE"
        confirm-label="Oui, supprimer mon compte"
        :show="$errors->accountDeletion->isNotEmpty()"
    >
        Vos titres en cours ne pourront plus être utilisés. Cette action est définitive.
        <x-slot:fields>
            <x-field id="delete-password" label="Votre mot de passe" :error="$errors->accountDeletion->first('password')" required>
                <div class="flex min-h-12 items-stretch overflow-hidden rounded-2xl bg-white ring-1 ring-inset ring-slate-300 focus-within:ring-2 focus-within:ring-primary-600" data-field-shell>
                    <input id="delete-password" name="password" type="password" autocomplete="current-password" required data-validate aria-describedby="delete-password-error" class="block w-full border-0 bg-transparent px-3.5 py-3 text-base focus:ring-0">
                </div>
            </x-field>
        </x-slot:fields>
    </x-confirm>
</x-layouts.espace>
