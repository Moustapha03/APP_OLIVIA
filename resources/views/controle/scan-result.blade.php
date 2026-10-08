{{-- Verdict of a scan (ScanController@store), readable at arm's length. --}}
@php
    /** @var \App\Actions\Scans\PassCheck $check */
@endphp

<x-layouts.base :title="$check->status->label().' · '.$check->title" body-class="bg-slate-950 text-white" theme-color="#020617">
    <main id="main">
        <x-scan-result :status="$check->status->value" :reason="$check->reason" :holder="$check->holder" :photo="$check->photoUrl" fullscreen>
            <div class="flex w-full max-w-sm flex-col gap-3">
                <p class="text-xl font-bold">{{ $check->title }}</p>

                @if ($check->details() !== [])
                    <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 rounded-2xl bg-black/15 px-4 py-3 text-left text-base">
                        @foreach ($check->details() as $label => $value)
                            <dt class="opacity-80">{{ $label }}</dt>
                            <dd class="font-semibold">{{ $value }}</dd>
                        @endforeach
                    </dl>
                @endif
            </div>

            <x-slot:action>
                <x-button :href="route('controle.scan')" variant="secondary" size="lg" block icon="scan-line" autofocus>Scanner le suivant</x-button>
            </x-slot:action>
        </x-scan-result>
    </main>
</x-layouts.base>
