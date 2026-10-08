{{--
    Chart in a card (Chart.js via the "chart" Alpine component), with the
    same figures in a table for screen readers.

    <x-chart-card title="Chiffre d’affaires" :chart="['type' => 'bar', 'labels' => [...], 'datasets' => [...], 'money' => true]" height="h-72" />
--}}
@props([
    'title',
    'subtitle' => null,
    'chart',
    'height' => 'h-72',
    'empty' => false,
])

@php
    $money = $chart['money'] ?? false;
    $format = fn ($v) => $money ? \App\Support\Money::format((int) $v) : number_format((int) $v, 0, ',', "\u{202F}");
@endphp

<x-card :title="$title" :subtitle="$subtitle" {{ $attributes }}>
    @if ($empty)
        <p class="py-10 text-center text-sm text-slate-500">Aucune donnée pour cette période.</p>
    @else
        <figure>
            <div x-data="chart" data-chart="{{ json_encode($chart) }}" class="relative {{ $height }}" aria-hidden="true">
                <canvas x-ref="canvas"></canvas>
            </div>
            <figcaption class="sr-only">
                <table>
                    <caption>{{ $title }}</caption>
                    <thead><tr><th>Libellé</th>@foreach ($chart['datasets'] as $set)<th>{{ $set['label'] }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($chart['labels'] as $i => $label)
                            <tr><th>{{ $label }}</th>@foreach ($chart['datasets'] as $set)<td>{{ $format($set['data'][$i] ?? 0) }}</td>@endforeach</tr>
                        @endforeach
                    </tbody>
                </table>
            </figcaption>
        </figure>
    @endif
</x-card>
