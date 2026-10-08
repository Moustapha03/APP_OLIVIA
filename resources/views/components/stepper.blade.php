{{--
    Progress of a short flow (3 steps max).

    <x-stepper :steps="['Arrêt', 'Formule', 'Paiement']" :current="2" />

    current is 1-based.
--}}
@props([
    'steps' => [],
    'current' => 1,
])

@php
    $total = count($steps);
@endphp

<nav {{ $attributes->class('space-y-3') }} aria-label="Progression">
    <p class="text-sm font-medium text-slate-600">
        Étape <span class="font-semibold text-slate-900">{{ $current }}</span> sur {{ $total }}
    </p>

    {{-- One segment per step: no inline style, CSP friendly --}}
    <div class="flex gap-1.5" aria-hidden="true">
        @foreach ($steps as $index => $label)
            <span @class([
                'h-2 flex-1 rounded-full transition-colors duration-300',
                'bg-primary-600' => $index + 1 <= $current,
                'bg-slate-200' => $index + 1 > $current,
            ])></span>
        @endforeach
    </div>

    <ol class="flex gap-1.5">
        @foreach ($steps as $index => $label)
            @php $number = $index + 1; @endphp
            <li
                @if ($number === $current) aria-current="step" @endif
                @class([
                    'flex min-w-0 flex-1 items-center gap-2 text-sm',
                    'font-semibold text-primary-700' => $number === $current,
                    'text-slate-700' => $number < $current,
                    'text-slate-500' => $number > $current,
                ])
            >
                <span @class([
                    'flex size-7 shrink-0 items-center justify-center rounded-full text-sm font-semibold',
                    'bg-primary-600 text-white' => $number === $current,
                    'bg-success-600 text-white' => $number < $current,
                    'bg-slate-200 text-slate-600' => $number > $current,
                ])>
                    @if ($number < $current)
                        <x-lucide-check class="size-4" aria-hidden="true" />
                        <span class="sr-only">Terminé :</span>
                    @else
                        {{ $number }}
                    @endif
                </span>
                <span class="truncate">{{ $label }}</span>
            </li>
        @endforeach
    </ol>
</nav>
