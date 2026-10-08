<x-layouts.controle title="Mes scans du jour">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Mes scans du jour</h1>
            <span class="text-base text-slate-300">{{ $scans->count() }} {{ $scans->count() > 1 ? 'scans' : 'scan' }}</span>
        </div>

        @if ($scans->isEmpty())
            <div class="rounded-3xl bg-white/5 px-6 py-10 text-center ring-1 ring-white/10">
                <x-lucide-scan-line class="mx-auto size-14 text-slate-400" aria-hidden="true" />
                <p class="mt-4 text-xl font-bold">Aucun scan aujourd’hui</p>
            </div>
        @else
            <ul class="flex flex-col gap-3">
                @foreach ($scans as $scan)
                    <li class="flex items-start gap-3 rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
                        <span class="w-12 shrink-0 pt-0.5 text-base font-semibold tabular-nums text-slate-300">{{ $scan->scanned_at->format('H:i') }}</span>
                        <div class="min-w-0 flex-1 space-y-1">
                            <p class="truncate text-lg font-semibold">
                                {{ match (true) {
                                    $scan->subscription !== null => $scan->subscription->subscriber->full_name,
                                    $scan->ticket !== null => 'Ticket du jour',
                                    default => 'Code inconnu',
                                } }}
                            </p>
                            <p class="text-base text-slate-300">{{ $scan->reason }}</p>
                        </div>
                        <x-badge :variant="$scan->result->variant()" :icon="$scan->result->icon()" class="shrink-0">{{ $scan->result->label() }}</x-badge>
                    </li>
                @endforeach
            </ul>
        @endif

        <x-button :href="route('controle.scan')" size="lg" block icon="scan-line">Scanner</x-button>
    </div>
</x-layouts.controle>
