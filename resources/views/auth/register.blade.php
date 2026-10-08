<x-layouts.focus title="Créer un compte" heading="Créer mon compte" subtitle="Pour acheter vos tickets et abonnements en ligne.">
    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <div class="grid gap-5 sm:grid-cols-2">
            <x-input name="first_name" label="Prénom" autocomplete="given-name" required autofocus />
            <x-input name="last_name" label="Nom" autocomplete="family-name" required />
        </div>

        <x-input
            name="phone"
            label="Numéro de téléphone"
            type="tel"
            prefix="+225"
            inputmode="numeric"
            autocomplete="tel-national"
            placeholder="07 00 00 00 00"
            hint="Il servira à vous connecter."
            required
        />

        <x-input
            name="email"
            label="Adresse e-mail"
            type="email"
            autocomplete="email"
            placeholder="nom@exemple.com"
            hint="Utile si vous oubliez votre mot de passe. Sans e-mail, passez au guichet."
        />

        <x-input
            name="password"
            label="Mot de passe"
            type="password"
            autocomplete="new-password"
            minlength="12"
            hint="12 caractères minimum. Astuce : une petite phrase, par exemple « mon bus pour bassam »."
            required
        />

        <x-input name="password_confirmation" label="Retapez le mot de passe" type="password" autocomplete="new-password" minlength="12" required />

        <x-checkbox name="privacy" required>
            J’accepte que mes données (nom, téléphone, achats) servent uniquement à gérer mes titres de transport, conformément à la loi ivoirienne n° 2013-450.
        </x-checkbox>

        <x-button type="submit" size="lg" block loading-text="Création du compte…">Créer mon compte</x-button>
    </form>

    <x-slot:after>
        Déjà un compte ?
        <a href="{{ route('login') }}" class="font-semibold text-primary-700 underline underline-offset-4">Se connecter</a>
    </x-slot:after>
</x-layouts.focus>
