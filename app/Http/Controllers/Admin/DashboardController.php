<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reports\DashboardStats;
use App\Support\SubscriptionMonths;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Back office home: revenue, subscribers and charts for a month.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('reports.view');

        $request->validate(['mois' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);
        $month = SubscriptionMonths::parse($request->string('mois')->toString() ?: null) ?? today()->startOfMonth();
        if ($month->isAfter(today()->endOfMonth())) {
            $month = today()->startOfMonth();
        }

        $stats = new DashboardStats($month);

        return view('admin.dashboard', [
            'month' => $stats->start(),
            'monthOptions' => self::monthOptions(),
            'kpis' => $stats->kpis(),
            'daily' => $stats->daily(),
            'monthly' => $stats->monthly(),
            'byMethod' => $stats->byMethod(),
            'byZone' => $stats->byZone(),
            'expensesByCategory' => $request->user()?->can('expenses.view') ? $stats->expensesByCategory() : [],
            'byFormula' => $stats->byFormula(),
            'topStops' => $stats->topStops(),
            'scans' => $stats->scansToday(),
            'latest' => $stats->latest(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function monthOptions(): array
    {
        $options = [];
        $month = today()->startOfMonth();

        for ($i = 0; $i < 24; $i++) {
            $options[$month->format('Y-m')] = ucfirst(SubscriptionMonths::label($month));
            $month->subMonthNoOverflow();
        }

        return $options;
    }
}
