<x-layouts.focus title="Mot de passe oublié" heading="Mot de passe oublié ?">
    <div class="space-y-6">
        <div class="space-y-4">
            <div class="flex gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
                    <x-lucide-mail class="size-5" aria-hidden="true" />
                </span>
                <div>
                    <p class="font-semibold text-slate-900">Vous avez confirmé un e-mail</p>
                    <p class="text-base text-slate-600">Recevez un lien pour choisir un nouveau mot de passe.</p>
                </div>
            </div>
            <div class="flex gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                    <x-lucide-store class="size-5" aria-hidden="true" />
                </span>
                <div>
                    <p class="font-semibold text-slate-900">Pas d’e-mail ?</p>
                    <p class="text-base text-slate-600">Passez au guichet avec votre pièce d’identité : on vous donne un mot de passe provisoire.</p>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
            @csrf
            <x-input name="email" label="Adresse e-mail" type="email" autocomplete="email" placeholder="nom@exemple.com" required autofocus />
            <x-button type="submit" size="lg" block icon="send" loading-text="Envoi…">Recevoir le lien</x-button>
        </form>
    </div>

    <x-slot:after>
        <a href="{{ route('login') }}" class="inline-flex items-center gap-1 font-semibold text-primary-700 underline underline-offset-4">
            <x-lucide-chevron-left class="size-4" aria-hidden="true" />
            Retour à la connexion
        </a>
    </x-slot:after>
</x-layouts.focus>
