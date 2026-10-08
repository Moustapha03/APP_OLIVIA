{{--
    "Installer l’application" card. Hidden until the browser offers the
    installation (resources/js/pwa.js); on iPhone, explains the Share menu.

    <x-install-app />
--}}
<div {{ $attributes->class('space-y-3') }}>
    <div data-pwa-install hidden class="flex items-center gap-4 rounded-card bg-slate-950 p-5 text-white">
        <img src="/icons/icon-192.png" alt="" class="size-12 shrink-0 rounded-2xl" width="48" height="48">
        <div class="min-w-0 flex-1">
            <p class="font-medium">Installer l’application</p>
            <p class="text-sm text-slate-400">Accès direct depuis l’écran d’accueil, titres visibles même sans réseau.</p>
        </div>
        <x-button type="button" size="sm" icon="download" data-pwa-install>Installer</x-button>
    </div>

    <div data-pwa-ios-hint hidden class="flex items-start gap-4 rounded-card bg-white p-5 ring-1 ring-slate-200/60">
        <img src="/icons/icon-192.png" alt="" class="size-12 shrink-0 rounded-2xl" width="48" height="48">
        <p class="text-sm text-slate-600">
            <span class="block font-medium text-slate-950">Installer sur iPhone</span>
            Touchez <x-lucide-share class="inline size-4 align-text-bottom" aria-label="Partager" /> puis « Sur l’écran d’accueil ».
        </p>
    </div>
</div>
