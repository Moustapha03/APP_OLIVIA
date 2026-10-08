@php
    $examples = [
        'valid' => ['Abonnement · Grand-Bassam', 'Awa Koné'],
        'refused' => ['Ticket déjà scanné il y a 4 minutes', null],
        'warning' => ['Arrêt plus loin que l’abonnement : complément à payer', 'Yao Kouassi'],
    ];
    [$reason, $holder] = $examples[$status];
@endphp

<x-layouts.base title="Aperçu · Scan" body-class="bg-slate-950">
    <main id="main">
        <x-scan-result :status="$status" :reason="$reason" :holder="$holder" fullscreen>
            <p class="opacity-90">Aperçu de la charte · données fictives</p>
            <x-slot:action>
                <x-button :href="route('design.index').'#scan'" variant="secondary" size="lg" block icon="scan-line">Scanner le suivant</x-button>
            </x-slot:action>
        </x-scan-result>
    </main>
</x-layouts.base>
