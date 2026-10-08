<x-layouts.focus title="Confirmation" heading="Confirmez votre mot de passe" subtitle="Cette page est protégée. Retapez votre mot de passe pour continuer.">
    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf
        <x-input name="password" label="Mot de passe" type="password" autocomplete="current-password" required autofocus />
        <x-button type="submit" size="lg" block>Confirmer</x-button>
    </form>
</x-layouts.focus>
