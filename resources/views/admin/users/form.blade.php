@php
    $editing = $user->exists;
    $isSelf = $isSelf ?? false;
    $canChangeIdentity = $canChangeIdentity ?? true;
    $temporaryPassword = session('temporary_password');
@endphp

<x-layouts.admin :title="$editing ? $user->name : 'Créer un compte'">
    <div class="max-w-2xl space-y-6">
        <x-page-header
            :title="$editing ? $user->name : 'Créer un compte'"
            :subtitle="$editing ? '+225 '.$user->formatted_phone : 'La personne recevra un mot de passe provisoire à changer à sa première connexion.'"
            :back="route('admin.users.index')"
        />

        @if ($temporaryPassword)
            <div class="rounded-2xl bg-warning-50 p-5 ring-2 ring-inset ring-warning-400" role="alert">
                <p class="flex items-center gap-2 font-semibold text-warning-800">
                    <x-lucide-key-round class="size-5" aria-hidden="true" />
                    Mot de passe provisoire
                </p>
                <p class="mt-3 select-all rounded-xl bg-white px-4 py-3 text-center font-mono text-3xl font-semibold tracking-wider text-slate-900 ring-1 ring-warning-100">{{ $temporaryPassword }}</p>
                <p class="mt-3 text-base text-warning-800">
                    Donnez-le à {{ $user->first_name }} en main propre. <strong>Il ne sera plus jamais affiché.</strong>
                    Il devra le changer dès sa connexion avec le numéro {{ $user->formatted_phone }}.
                </p>
            </div>
        @endif

        @if ($editing && $user->isLocked())
            <x-alert variant="warning" title="Compte bloqué jusqu’à {{ $user->locked_until?->format('H\hi') }}">
                @can('resetPassword', $user)
                    Trop de mots de passe faux. Un mot de passe provisoire le débloque tout de suite.
                @else
                    Trop de mots de passe faux. Il se débloque seul à cette heure, ou plus tôt par un super-administrateur.
                @endcan
            </x-alert>
        @endif

        <x-card title="Identité et accès">
            <form method="POST" action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}" class="space-y-5">
                @csrf
                @if ($editing)
                    @method('PUT')
                @endif

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-input name="first_name" label="Prénom" :value="$user->first_name" required :autofocus="! $editing" />
                    <x-input name="last_name" label="Nom" :value="$user->last_name" required />
                </div>
                @if ($canChangeIdentity)
                    <x-input name="phone" label="Téléphone" type="tel" prefix="+225" inputmode="numeric" :value="$user->exists ? $user->formatted_phone : null" hint="Sert à se connecter. Vérifiez la pièce d’identité avant de le changer." required />
                    <x-input name="email" label="Adresse e-mail" type="email" :value="$user->email" />
                @else
                    {{-- Staff login: only a super-admin may change it (UserPolicy::changeIdentity). --}}
                    <x-input name="phone" label="Téléphone" type="tel" prefix="+225" :value="$user->formatted_phone" hint="Identifiant de connexion d’un membre du personnel : seul un super-administrateur peut le changer." readonly required />
                    <x-input name="email" label="Adresse e-mail" type="email" :value="$user->email" readonly />
                @endif

                @if ($isSelf)
                    <input type="hidden" name="role" value="{{ $currentRole }}">
                    <input type="hidden" name="status" value="{{ $user->status->value }}">
                    <x-alert variant="info">C’est votre compte : votre rôle et votre statut ne peuvent être changés que par une autre personne.</x-alert>
                @else
                    <x-select name="role" label="Rôle" :options="$roleOptions" :value="$currentRole" placeholder="Choisissez un rôle" required />

                    @if ($editing)
                        <x-select
                            name="status"
                            label="Statut"
                            :options="collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()"
                            :value="$user->status->value"
                            hint="Un compte désactivé est déconnecté immédiatement."
                            required
                        />
                    @endif
                @endif

                <x-button type="submit" size="lg" block>{{ $editing ? 'Enregistrer' : 'Créer le compte' }}</x-button>
            </form>
        </x-card>

        @if ($editing)
            @can('resetPassword', $user)
                <x-card title="Mot de passe oublié ?" subtitle="Crée un mot de passe provisoire, débloque le compte, et oblige la personne à en choisir un nouveau.">
                    <x-button variant="secondary" block icon="key-round" data-modal-open="reset-password">Donner un mot de passe provisoire</x-button>
                </x-card>

                <x-confirm
                    name="reset-password"
                    title="Donner un mot de passe provisoire ?"
                    :action="route('admin.users.password.reset', $user)"
                    confirm-label="Oui, créer le mot de passe"
                    variant="warning"
                >
                    Vérifiez d’abord l’identité de {{ $user->name }}. Son mot de passe actuel ne marchera plus.
                </x-confirm>
            @endcan
        @endif
    </div>
</x-layouts.admin>
