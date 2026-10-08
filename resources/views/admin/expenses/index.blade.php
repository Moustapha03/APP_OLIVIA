@php
    use App\Support\Money;
    $monthLabel = $filters['month'] ? ucfirst(\App\Support\SubscriptionMonths::label(\App\Support\SubscriptionMonths::parse($filters['month']))) : 'Tous les mois';
    $variation = $previousTotal !== null && $previousTotal > 0 ? (int) round(($total - $previousTotal) / $previousTotal * 100) : null;
    $withSpending = $byCategory->filter(fn ($row) => $row['total'] > 0);
@endphp

<x-layouts.admin title="Dépenses">
    <div class="space-y-6">
        <x-page-header title="Dépenses" :subtitle="$monthLabel.' · le total et le montant de chaque type de dépense.'">
            <x-slot:actions>
                @can('reports.export')
                    <x-button :href="route('admin.expenses.export', request()->query())" variant="secondary" icon="download" data-no-loading>Exporter (CSV Excel)</x-button>
                @endcan
                @can('create', App\Models\Expense::class)
                    <x-button :href="route('admin.expenses.create')" icon="plus">Nouvelle dépense</x-button>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <x-card>
            <form method="GET" action="{{ route('admin.expenses.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-[repeat(4,minmax(0,1fr))_minmax(0,1.4fr)_auto] lg:items-end" data-no-loading>
                <x-select name="mois" label="Mois" :options="$monthOptions" :value="$filters['month'] ?? ''" />
                <x-select name="type" label="Type" :options="['' => 'Tous les types'] + $categories->pluck('name', 'id')->all()" :value="$filters['category'] ?? ''" />
                <x-select name="vehicule" label="Véhicule" :options="['' => 'Tous'] + $vehicleOptions" :value="$filters['vehicle'] ?? ''" />
                <x-select name="moyen" label="Paiement" :options="['' => 'Tous'] + collect($methods)->mapWithKeys(fn ($m) => [$m->value => $m->label()])->all()" :value="$filters['method'] ?? ''" />
                <x-input name="q" label="Rechercher" icon="search" :value="$filters['q']" placeholder="Libellé, référence, remarque" />
                <x-button type="submit" variant="secondary" icon="filter">Filtrer</x-button>
            </form>
        </x-card>

        {{-- Total and total per type --}}
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <section class="rounded-card bg-slate-950 p-6 text-white sm:p-8" aria-labelledby="expenses-total">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h2 id="expenses-total" class="text-base text-slate-400">Total des dépenses · {{ $monthLabel }}</h2>
                    @if ($variation !== null)
                        <span class="rounded-full bg-white/10 px-3 py-1 text-sm font-medium">{{ ($variation >= 0 ? '+' : '').$variation }} % vs mois précédent</span>
                    @endif
                </div>
                <p class="mt-4 text-4xl font-light tracking-tight tabular-nums sm:text-6xl"><x-money :amount="$total" /></p>
                <p class="mt-1 text-sm text-slate-400">{{ $count }} dépense(s){{ $previousTotal !== null ? ' · mois précédent : '.Money::format($previousTotal) : '' }}</p>

                <ul class="mt-6 space-y-3">
                    @forelse ($withSpending as $row)
                        @php $share = $total > 0 ? $row['total'] / $total * 100 : 0; @endphp
                        <li>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <a href="{{ route('admin.expenses.index', [...request()->query(), 'type' => $row['category']->id]) }}" class="truncate text-slate-200 hover:text-white hover:underline">{{ $row['category']->name }}</a>
                                <span class="shrink-0 font-medium tabular-nums">{{ Money::format($row['total']) }} <span class="text-slate-400">· {{ round($share) }} %</span></span>
                            </div>
                            <svg class="mt-1.5 h-1.5 w-full" viewBox="0 0 100 2" preserveAspectRatio="none" aria-hidden="true">
                                <rect width="100" height="2" rx="1" fill="#333333" />
                                <rect width="{{ max(1, round($share, 1)) }}" height="2" rx="1" fill="#e4603a" />
                            </svg>
                        </li>
                    @empty
                        <li class="text-sm text-slate-400">Aucune dépense sur cette période.</li>
                    @endforelse
                </ul>
            </section>

            <x-chart-card title="Répartition par type" :subtitle="$monthLabel" height="h-72" :empty="$withSpending->isEmpty()"
                :chart="['type' => 'doughnut', 'money' => true, 'labels' => $withSpending->map(fn ($r) => $r['category']->name)->values()->all(), 'datasets' => [['label' => 'Montant', 'data' => $withSpending->pluck('total')->values()->all()]]]" />
        </div>

        {{-- List --}}
        <x-table caption="Dépenses" empty="Aucune dépense pour ces critères.">
            <x-slot:head>
                <th>Date</th><th>Type</th><th>Libellé</th><th>Paiement</th><th><span class="sr-only">Justificatif</span></th><th class="text-right">Montant</th><th><span class="sr-only">Actions</span></th>
            </x-slot:head>
            @foreach ($expenses as $expense)
                <tr>
                    <td class="whitespace-nowrap tabular-nums">{{ $expense->spent_on->format('d/m/Y') }}</td>
                    <td class="whitespace-nowrap">{{ $expense->category->name }}</td>
                    <td>
                        <span class="font-medium">{{ $expense->label }}</span>
                        @if ($expense->vehicle)<span class="block text-sm text-slate-500">{{ $expense->vehicle->name }}</span>@endif
                    </td>
                    <td class="whitespace-nowrap">{{ $expense->payment_method->label() }}@if ($expense->reference)<span class="block font-mono text-xs text-slate-500">{{ $expense->reference }}</span>@endif</td>
                    <td>
                        @if ($expense->hasAttachment())
                            <a href="{{ route('admin.expenses.attachment', $expense) }}" target="_blank" rel="noopener" class="inline-flex size-10 items-center justify-center rounded-full text-slate-600 hover:bg-slate-100" aria-label="Voir le justificatif" title="Justificatif">
                                <x-lucide-paperclip class="size-5" aria-hidden="true" />
                            </a>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-right"><x-money :amount="$expense->amount" class="font-semibold" /></td>
                    <td class="whitespace-nowrap text-right">
                        @can('update', $expense)
                            <x-button :href="route('admin.expenses.edit', $expense)" variant="ghost" size="sm" icon="pencil">Modifier</x-button>
                        @endcan
                        @can('delete', $expense)
                            <x-button variant="ghost" size="sm" icon="trash-2" data-modal-open="delete-expense-{{ $expense->id }}">Supprimer</x-button>
                        @endcan
                    </td>
                </tr>
            @endforeach
            @if ($expenses->hasPages())
                <x-slot:footer>{{ $expenses->links() }}</x-slot:footer>
            @endif
        </x-table>

        @foreach ($expenses as $expense)
            @can('delete', $expense)
                <x-confirm :name="'delete-expense-'.$expense->id" title="Supprimer cette dépense ?" :action="route('admin.expenses.destroy', $expense)" method="DELETE" confirm-label="Oui, supprimer">
                    {{ $expense->spent_on->format('d/m/Y') }} · {{ $expense->category->name }} · {{ $expense->label }} : {{ Money::format($expense->amount) }}.
                    Elle ne comptera plus dans les totaux ; la suppression reste inscrite au journal d’activité.
                </x-confirm>
            @endcan
        @endforeach

        {{-- Types of expense --}}
        @can('expenses.manage')
            <x-card title="Types de dépenses" subtitle="Ajoutez, renommez ou retirez un type. Un type déjà utilisé est archivé : il reste dans les totaux passés.">
                <ul class="divide-y divide-slate-100">
                    @foreach ($categories as $category)
                        <li class="py-3">
                            <form method="POST" action="{{ route('admin.expenses.categories.update', $category) }}" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                @csrf
                                @method('PUT')
                                <label for="category-{{ $category->id }}" class="sr-only">Nom du type</label>
                                <input id="category-{{ $category->id }}" name="name" value="{{ $category->name }}" required maxlength="80"
                                    class="block min-h-11 w-full min-w-0 flex-1 rounded-full border-0 bg-white px-5 text-base ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-primary-600">
                                <label class="inline-flex min-h-11 items-center gap-2 text-sm text-slate-700">
                                    <input type="checkbox" name="is_active" value="1" @checked($category->is_active) class="size-5 rounded border-slate-300 text-primary-600 focus:ring-primary-600">
                                    Proposé
                                </label>
                                <div class="flex gap-1">
                                    <x-button type="submit" variant="secondary" size="sm" icon="save">Enregistrer</x-button>
                                    <x-button variant="ghost" size="sm" icon="trash-2" data-modal-open="delete-category-{{ $category->id }}">Supprimer</x-button>
                                </div>
                            </form>
                            @error('name', 'category_'.$category->id)<p class="mt-1 text-sm font-medium text-danger-700">{{ $message }}</p>@enderror
                            <x-confirm :name="'delete-category-'.$category->id" :title="'Supprimer le type « '.$category->name.' » ?'" :action="route('admin.expenses.categories.destroy', $category)" method="DELETE" confirm-label="Oui, supprimer">
                                S’il a déjà des dépenses, il est seulement archivé (plus proposé), pour garder les totaux passés.
                            </x-confirm>
                        </li>
                    @endforeach
                </ul>

                <form method="POST" action="{{ route('admin.expenses.categories.store') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                    @csrf
                    <div class="min-w-0 flex-1">
                        <x-input name="name" id="new-category" label="Nouveau type" placeholder="Ex. : Lavage des bus" required />
                    </div>
                    <x-button type="submit" icon="plus">Ajouter</x-button>
                </form>
                @error('name', 'category')<p class="mt-2 text-sm font-medium text-danger-700">{{ $message }}</p>@enderror
            </x-card>
        @endcan
    </div>
</x-layouts.admin>
