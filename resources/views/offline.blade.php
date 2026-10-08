{{-- Shown by the service worker (public/sw.js) when a page is not available without network. --}}
<x-layouts.focus title="Hors ligne" heading="Pas de connexion internet" subtitle="Cette page n’est pas disponible sans réseau. Ce qui marche quand même :">
    <div class="space-y-3">
        <x-button href="/espace/titres" variant="dark" size="lg" block icon="qr-code">Mes titres enregistrés</x-button>
        <x-button href="/controle/scanner" variant="secondary" size="lg" block icon="scan-line">Scanner (convoyeur)</x-button>
        <x-button href="/dashboard" variant="ghost" block icon="refresh-cw">Réessayer</x-button>
    </div>

    <x-slot:after>
        Vos titres s’affichent hors ligne s’ils ont été ouverts au moins une fois avec du réseau sur ce téléphone.
    </x-slot:after>
</x-layouts.focus>
