<x-layouts.focus
    title="Mot de passe"
    :heading="$forced ? 'Choisissez votre mot de passe' : 'Changer mon mot de passe'"
    :subtitle="$forced ? 'Vous avez reçu un mot de passe provisoire. Remplacez-le par un mot de passe que vous seul connaissez.' : null"
>
    <form method="POST" action="{{ route('account.password.update') }}" class="space-y-5">
        @csrf
        @method('PUT')

        <x-input
            name="current_password"
            :label="$forced ? 'Mot de passe provisoire' : 'Mot de passe actuel'"
            type="password"
            autocomplete="current-password"
            required
            autofocus
        />

        <x-input
            name="password"
            label="Nouveau mot de passe"
            type="password"
            autocomplete="new-password"
            minlength="12"
            hint="12 caractères minimum. Astuce : une petite phrase facile à retenir."
            required
        />

        <x-input name="password_confirmation" label="Retapez le nouveau mot de passe" type="password" autocomplete="new-password" minlength="12" required />

        <x-button type="submit" size="lg" block loading-text="Enregistrement…">Enregistrer</x-button>
    </form>

    <x-slot:after>
        @if ($forced)
            <x-logout-button class="inline-block" />
        @else
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 font-semibold text-primary-700 underline underline-offset-4">
                <x-lucide-chevron-left class="size-4" aria-hidden="true" />
                Retour
            </a>
        @endif
    </x-slot:after>
</x-layouts.focus>
