<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Vehicle;
use App\Support\SubscriptionMonths;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Agency expenses: list by month with the total and the total per type,
 * create, edit, delete (archive), receipt (justificatif), CSV export.
 */
class ExpenseController extends Controller
{
    private const DISK = 'local';

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Expense::class);

        $filters = $this->filters($request);
        $expenses = $this->query($filters)->with(['category', 'vehicle', 'author'])->paginate(50)->withQueryString();

        // Total per type over the same filters (archived expenses never count).
        $sums = $this->query($filters)->reorder()
            ->selectRaw('expense_category_id, COUNT(*) as n, COALESCE(SUM(amount), 0) as total')
            ->groupBy('expense_category_id')
            ->get()
            ->keyBy('expense_category_id');

        $categories = ExpenseCategory::query()->orderBy('name')->get();
        $byCategory = $categories
            ->map(fn (ExpenseCategory $c) => ['category' => $c, 'count' => (int) ($sums[$c->id]->n ?? 0), 'total' => (int) ($sums[$c->id]->total ?? 0)])
            ->filter(fn (array $row) => $row['category']->is_active || $row['total'] > 0)
            ->sortByDesc('total')
            ->values();

        $month = SubscriptionMonths::parse($filters['month']);
        $previousTotal = $month !== null
            ? (int) $this->query([...$filters, 'month' => $month->copy()->subMonthNoOverflow()->format('Y-m')])->reorder()->sum('amount')
            : null;

        return view('admin.expenses.index', [
            'expenses' => $expenses,
            'filters' => $filters,
            'total' => (int) $sums->sum('total'),
            'count' => (int) $sums->sum('n'),
            'previousTotal' => $previousTotal,
            'byCategory' => $byCategory,
            'categories' => $categories,
            'monthOptions' => self::monthOptions(),
            'vehicleOptions' => Vehicle::query()->orderBy('name')->pluck('name', 'id')->all(),
            'methods' => PaymentMethod::forExpenses(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Expense::class);

        return view('admin.expenses.form', $this->formData(new Expense(['spent_on' => today(), 'payment_method' => PaymentMethod::Cash])));
    }

    public function store(ExpenseRequest $request): RedirectResponse
    {
        $expense = new Expense($request->expenseData());
        $expense->forceFill(['created_by' => $request->user()?->id, 'updated_by' => $request->user()?->id]);
        $this->storeAttachment($expense, $request->file('attachment'));
        $expense->save();

        return redirect()->route('admin.expenses.index', ['mois' => $expense->spent_on->format('Y-m')])
            ->with('success', 'Dépense enregistrée : '.$expense->label.'.');
    }

    public function edit(Expense $expense): View
    {
        Gate::authorize('update', $expense);

        return view('admin.expenses.form', $this->formData($expense));
    }

    public function update(ExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $expense->fill($request->expenseData());
        $expense->forceFill(['updated_by' => $request->user()?->id]);

        if ($request->boolean('remove_attachment') && $expense->attachment_path !== null) {
            Storage::disk(self::DISK)->delete($expense->attachment_path);
            $expense->attachment_path = null;
        }
        $this->storeAttachment($expense, $request->file('attachment'));
        $expense->save();

        return redirect()->route('admin.expenses.index', ['mois' => $expense->spent_on->format('Y-m')])
            ->with('success', 'Dépense modifiée : '.$expense->label.'.');
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse
    {
        Gate::authorize('delete', $expense);

        $expense->forceFill(['updated_by' => $request->user()?->id])->save();
        $expense->delete();

        return redirect()->route('admin.expenses.index', ['mois' => $expense->spent_on->format('Y-m')])
            ->with('success', 'Dépense supprimée : '.$expense->label.'. Elle reste dans le journal d’activité.');
    }

    /**
     * The receipt (photo or PDF) of an expense, never public.
     */
    public function attachment(Expense $expense): Response
    {
        Gate::authorize('view', $expense);
        abort_unless($expense->attachment_path !== null && Storage::disk(self::DISK)->exists($expense->attachment_path), 404);

        $extension = pathinfo($expense->attachment_path, PATHINFO_EXTENSION);

        return Storage::disk(self::DISK)->response($expense->attachment_path, 'justificatif-depense-'.$expense->id.'.'.$extension);
    }

    /**
     * CSV for Excel (; separator, UTF-8 BOM, TOTAL line), formulas neutralised.
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Expense::class);

        $filters = $this->filters($request);
        activity('audit')->causedBy($request->user())->event('expenses.exported')->withProperties(['filters' => $filters])->log('Export CSV des dépenses');

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\u{FEFF}");
            $cell = fn (mixed $c) => preg_match('/^[=+\-@\t\r]/', (string) $c) === 1 ? "'".$c : (string) $c;
            $line = fn (array $cells) => fwrite($out, implode(';', array_map(fn ($c) => '"'.str_replace('"', '""', $cell($c)).'"', $cells))."\r\n");

            $line(['Date', 'Type', 'Libellé', 'Véhicule', 'Paiement', 'Réf.', 'Montant FCFA']);
            $total = 0;
            $this->query($filters)->with(['category', 'vehicle'])->chunk(500, function ($expenses) use ($line, &$total) {
                foreach ($expenses as $expense) {
                    /** @var Expense $expense */
                    $total += $expense->amount;
                    $line([$expense->spent_on->format('d/m/Y'), $expense->category->name, $expense->label, $expense->vehicle?->name, $expense->payment_method->label(), $expense->reference, $expense->amount]);
                }
            });
            $line(['', '', '', '', '', 'TOTAL', $total]);
            fclose($out);
        }, 'depenses-'.($filters['month'] ?? 'toutes').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Expense $expense): array
    {
        return [
            'expense' => $expense,
            'categoryOptions' => ExpenseCategory::query()
                ->where(fn (Builder $q) => $q->where('is_active', true)->orWhere('id', $expense->expense_category_id))
                ->orderBy('name')->pluck('name', 'id')->all(),
            'vehicleOptions' => ['' => 'Aucun véhicule'] + Vehicle::query()->orderBy('name')->pluck('name', 'id')->all(),
            'methods' => PaymentMethod::forExpenses(),
        ];
    }

    private function storeAttachment(Expense $expense, mixed $file): void
    {
        if (! $file instanceof UploadedFile) {
            return;
        }

        if ($expense->attachment_path !== null) {
            Storage::disk(self::DISK)->delete($expense->attachment_path);
        }

        // Random name, private disk: only served through attachment() above.
        $expense->attachment_path = (string) $file->store('justificatifs', self::DISK);
    }

    /**
     * @return array{month: string|null, category: int|null, vehicle: int|null, method: string|null, q: string|null}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'mois' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'type' => ['nullable', 'integer'],
            'vehicule' => ['nullable', 'integer'],
            'moyen' => ['nullable', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return [
            // Current month by default; "Tous les mois" sends an empty value.
            'month' => $request->has('mois') ? ($validated['mois'] ?? null) : today()->format('Y-m'),
            'category' => isset($validated['type']) ? (int) $validated['type'] : null,
            'vehicle' => isset($validated['vehicule']) ? (int) $validated['vehicule'] : null,
            'method' => $validated['moyen'] ?? null,
            'q' => isset($validated['q']) ? trim($validated['q']) : null,
        ];
    }

    /**
     * @param  array{month: string|null, category: int|null, vehicle: int|null, method: string|null, q: string|null}  $filters
     * @return Builder<Expense>
     */
    private function query(array $filters): Builder
    {
        $month = SubscriptionMonths::parse($filters['month']);

        return Expense::query()
            ->when($month, fn (Builder $q, Carbon $m) => $q->whereBetween('spent_on', [$m->copy()->startOfMonth()->toDateString(), $m->copy()->endOfMonth()->toDateString()]))
            ->when($filters['category'], fn (Builder $q, int $id) => $q->where('expense_category_id', $id))
            ->when($filters['vehicle'], fn (Builder $q, int $id) => $q->where('vehicle_id', $id))
            ->when($filters['method'], fn (Builder $q, string $method) => $q->where('payment_method', $method))
            ->when($filters['q'], fn (Builder $q, string $search) => $q->where(fn (Builder $w) => $w
                ->where('label', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%")
                ->orWhere('notes', 'like', "%{$search}%")))
            ->orderByDesc('spent_on')
            ->orderByDesc('id');
    }

    /**
     * @return array<string, string>
     */
    private static function monthOptions(): array
    {
        $options = ['' => 'Tous les mois'];
        $month = today()->startOfMonth();

        for ($i = 0; $i < 24; $i++) {
            $options[$month->format('Y-m')] = ucfirst(SubscriptionMonths::label($month));
            $month->subMonthNoOverflow();
        }

        return $options;
    }
}
