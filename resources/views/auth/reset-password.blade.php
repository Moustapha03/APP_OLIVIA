<x-layouts.focus title="Nouveau mot de passe" heading="Choisir un nouveau mot de passe">
    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-input name="email" label="Adresse e-mail" type="email" :value="$request->query('email')" autocomplete="email" required />

        <x-input
            name="password"
            label="Nouveau mot de passe"
            type="password"
            autocomplete="new-password"
            minlength="12"
            hint="12 caractères minimum."
            required
            autofocus
        />

        <x-input name="password_confirmation" label="Retapez le mot de passe" type="password" autocomplete="new-password" minlength="12" required />

        <x-button type="submit" size="lg" block loading-text="Enregistrement…">Enregistrer</x-button>
    </form>
</x-layouts.focus>
