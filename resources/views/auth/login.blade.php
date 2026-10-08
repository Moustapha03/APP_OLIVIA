<x-layouts.focus title="Connexion" heading="Se connecter" subtitle="Avec votre numéro de téléphone et votre mot de passe.">
    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <x-input
            name="login"
            label="Numéro de téléphone"
            type="text"
            inputmode="tel"
            autocomplete="username"
            placeholder="07 00 00 00 00"
            hint="Le personnel peut aussi utiliser son adresse e-mail."
            required
            autofocus
        />

        <x-input name="password" label="Mot de passe" type="password" autocomplete="current-password" required />

        <x-checkbox name="remember" checked>Rester connecté sur ce téléphone</x-checkbox>

        <x-button type="submit" size="lg" block loading-text="Connexion…">Se connecter</x-button>

        <p class="text-center">
            <a href="{{ route('password.request') }}" class="inline-flex min-h-11 items-center rounded-xl px-2 text-base font-medium text-primary-700 underline underline-offset-4 hover:bg-primary-50">
                Mot de passe oublié ?
            </a>
        </p>
    </form>

    <x-slot:after>
        Pas encore de compte ?
        <a href="{{ route('register') }}" class="font-semibold text-primary-700 underline underline-offset-4">Créer un compte</a>
    </x-slot:after>
</x-layouts.focus>
