<x-layouts.controle title="Aperçu · Contrôle" :preview="true">
    <x-slot:trip>Bus 12 · départ 07 h 30 · Grand-Bassam</x-slot:trip>

    <x-design.preview-banner tone="dark" />

    <div class="flex flex-1 flex-col gap-6">
        <div class="grid grid-cols-2 gap-3">
            <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
                <p class="text-sm text-slate-300">Passagers</p>
                <p class="text-4xl font-bold tabular-nums">27</p>
            </div>
            <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
                <p class="text-sm text-slate-300">Refusés</p>
                <p class="text-4xl font-bold tabular-nums text-danger-500">2</p>
            </div>
        </div>

        <a href="{{ route('design.scan', 'valid') }}" class="flex flex-1 flex-col items-center justify-center gap-4 rounded-3xl bg-primary-600 px-6 py-12 text-center shadow-lifted transition active:scale-[0.98]">
            <x-lucide-scan-line class="size-24" aria-hidden="true" />
            <span class="text-3xl font-bold">Scanner un titre</span>
            <span class="text-base text-primary-100">Visez le QR code avec la caméra</span>
        </a>

        <div class="space-y-2">
            <p class="text-sm font-semibold text-slate-300">Derniers scans</p>
            <ul class="divide-y divide-white/10 overflow-hidden rounded-2xl bg-white/5 ring-1 ring-white/10">
                @foreach ([['07:42', 'Abonnement · Bonoua', 'success', 'Valide'], ['07:41', 'Ticket déjà scanné', 'danger', 'Refusé'], ['07:40', 'Ticket · Anani', 'success', 'Valide']] as [$time, $label, $variant, $status])
                    <li class="flex items-center justify-between gap-3 px-4 py-3">
                        <div>
                            <p class="font-medium">{{ $label }}</p>
                            <p class="text-sm text-slate-400 tabular-nums">{{ $time }}</p>
                        </div>
                        <x-badge :variant="$variant">{{ $status }}</x-badge>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</x-layouts.controle>
