<x-layouts.controle title="Mon voyage">
    <div class="space-y-6">
        <div class="rounded-3xl bg-white/5 px-6 py-10 text-center ring-1 ring-white/10">
            <x-lucide-bus class="mx-auto size-16 text-slate-400" aria-hidden="true" />
            <p class="mt-4 text-2xl font-bold">Aucun voyage en cours</p>
            <p class="mt-1 text-base text-slate-300">Le démarrage d’un voyage arrive bientôt.</p>
        </div>

        <div class="flex flex-col gap-2 rounded-2xl bg-white/5 p-3 ring-1 ring-white/10">
            <a href="{{ route('account.password.edit') }}" class="inline-flex min-h-11 items-center gap-2 rounded-xl px-3 text-sm font-semibold text-slate-200 hover:bg-white/10">
                <x-lucide-key-round class="size-5" aria-hidden="true" />
                Changer mon mot de passe
            </a>
            <x-logout-button tone="light" />
        </div>
    </div>
</x-layouts.controle>
