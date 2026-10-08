<x-error-page code="419" title="La page a expiré" icon="clock" tone="warning">
    Vous êtes resté longtemps sans rien faire. Rechargez la page et recommencez.
    <x-slot:actions>
        <x-button :href="url()->previous()" size="lg" block icon="refresh-cw">Recharger la page</x-button>
    </x-slot:actions>
</x-error-page>
