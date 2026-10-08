<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Vehicle;
use App\Services\Reports\DashboardStats;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Carbon::setTestNow('2026-10-08 10:00:00');
    Storage::fake('local');
    $this->fuel = ExpenseCategory::factory()->create(['name' => 'Carburant']);
    $this->repairs = ExpenseCategory::factory()->create(['name' => 'Entretien et réparations']);
    $this->accountant = userWithRole(RoleName::Comptable);
});

// -- Create, edit, delete -----------------------------------------------------------------

it('records an expense with its type, amount typed with spaces, vehicle and receipt', function () {
    $bus = Vehicle::factory()->create(['name' => 'Bus 12']);

    $this->actingAs($this->accountant)->post(route('admin.expenses.store'), [
        'spent_on' => '2026-10-07', 'expense_category_id' => $this->fuel->id, 'label' => '  Gasoil   Bus 12 ', 'amount' => '45 000',
        'payment_method' => 'wave', 'reference' => 'T_123', 'vehicle_id' => $bus->id,
        'attachment' => UploadedFile::fake()->create('facture.pdf', 120, 'application/pdf'),
    ])->assertRedirect(route('admin.expenses.index', ['mois' => '2026-10']))->assertSessionHas('success');

    $expense = Expense::query()->sole();
    expect($expense)
        ->amount->toBe(45000)
        ->label->toBe('Gasoil Bus 12')
        ->payment_method->toBe(PaymentMethod::Wave)
        ->vehicle_id->toBe($bus->id)
        ->created_by->toBe($this->accountant->id)
        ->and($expense->hasAttachment())->toBeTrue();
    Storage::disk('local')->assertExists($expense->attachment_path);

    $this->actingAs($this->accountant)->get(route('admin.expenses.attachment', $expense))->assertOk();
});

it('edits an expense and keeps the old values in the activity log', function () {
    $expense = Expense::factory()->create(['expense_category_id' => $this->fuel->id, 'amount' => 30000, 'label' => 'Gasoil']);

    $this->actingAs($this->accountant)->put(route('admin.expenses.update', $expense), [
        'spent_on' => '2026-10-05', 'expense_category_id' => $this->repairs->id, 'label' => 'Vidange', 'amount' => '35000', 'payment_method' => 'cash',
    ])->assertSessionHas('success');

    expect($expense->fresh())->amount->toBe(35000)->expense_category_id->toBe($this->repairs->id)->updated_by->toBe($this->accountant->id);

    $log = Activity::query()->where('subject_type', Expense::class)->where('event', 'updated')->sole();
    expect($log->properties['old']['amount'])->toBe(30000)->and($log->properties['attributes']['amount'])->toBe(35000);
});

it('deletes (archives) an expense: it no longer counts but stays traceable', function () {
    $expense = Expense::factory()->create(['expense_category_id' => $this->fuel->id, 'amount' => 30000]);

    $this->actingAs($this->accountant)->delete(route('admin.expenses.destroy', $expense))->assertSessionHas('success');

    expect(Expense::query()->count())->toBe(0)
        ->and(Expense::withTrashed()->sole()->trashed())->toBeTrue()
        ->and(Activity::query()->where('subject_type', Expense::class)->where('event', 'deleted')->exists())->toBeTrue();
});

it('validates the expense form in French', function (array $input, string $field) {
    $this->actingAs($this->accountant)->post(route('admin.expenses.store'), [
        ...['spent_on' => '2026-10-07', 'expense_category_id' => $this->fuel->id, 'label' => 'Gasoil', 'amount' => '1000', 'payment_method' => 'cash'],
        ...$input,
    ])->assertSessionHasErrors($field);
})->with([
    'future date' => [['spent_on' => '2026-10-09'], 'spent_on'],
    'zero' => [['amount' => '0'], 'amount'],
    'too high' => [['amount' => '999999999'], 'amount'],
    'no label' => [['label' => ''], 'label'],
    'unknown type' => [['expense_category_id' => 999999], 'expense_category_id'],
    'online is not a way to pay a supplier' => [['payment_method' => 'online'], 'payment_method'],
    'receipt not an image or pdf' => [['attachment' => UploadedFile::fake()->create('virus.exe', 10)], 'attachment'],
]);

// -- Totals ----------------------------------------------------------------------------------

it('shows the total and the total per type for the month, and filters', function () {
    Expense::factory()->create(['expense_category_id' => $this->fuel->id, 'amount' => 40000, 'label' => 'Gasoil Bus 12', 'spent_on' => '2026-10-02']);
    Expense::factory()->create(['expense_category_id' => $this->fuel->id, 'amount' => 20000, 'label' => 'Gasoil Bus 15', 'spent_on' => '2026-10-04']);
    Expense::factory()->create(['expense_category_id' => $this->repairs->id, 'amount' => 15000, 'label' => 'Pneu', 'spent_on' => '2026-10-05']);
    Expense::factory()->create(['expense_category_id' => $this->repairs->id, 'amount' => 99000, 'label' => 'Moteur', 'spent_on' => '2026-09-20']);

    $this->actingAs(userWithRole(RoleName::Direction))->get(route('admin.expenses.index'))
        ->assertOk()
        ->assertSee("75\u{00A0}000\u{00A0}FCFA")           // total of October
        ->assertSeeInOrder(['Carburant', "60\u{00A0}000\u{00A0}FCFA", 'Entretien et réparations', "15\u{00A0}000\u{00A0}FCFA"])
        ->assertDontSee('Moteur')
        ->assertDontSee('Nouvelle dépense');                 // direction: read only

    $this->actingAs($this->accountant)->get(route('admin.expenses.index', ['type' => $this->repairs->id, 'mois' => '']))
        ->assertSee('Moteur')->assertSee('Pneu')->assertDontSee('Gasoil');
    $this->actingAs($this->accountant)->get(route('admin.expenses.index', ['q' => 'bus 15']))
        ->assertSee('Gasoil Bus 15')->assertDontSee('Gasoil Bus 12');
});

it('exports the expenses in CSV with the total, formulas neutralised', function () {
    Expense::factory()->create(['expense_category_id' => $this->fuel->id, 'amount' => 40000, 'label' => '=1+1']);

    $csv = $this->actingAs($this->accountant)->get(route('admin.expenses.export', ['mois' => '2026-10']))->streamedContent();

    expect($csv)->toContain('"Carburant";"\'=1+1"')->toContain('"TOTAL";"40000"');
});

// -- Types -------------------------------------------------------------------------------------

it('adds, renames and removes types; a used type is archived', function () {
    $this->actingAs($this->accountant)->post(route('admin.expenses.categories.store'), ['name' => 'Lavage des bus'])->assertSessionHas('success');
    $wash = ExpenseCategory::query()->where('name', 'Lavage des bus')->sole();

    $this->actingAs($this->accountant)->put(route('admin.expenses.categories.update', $wash), ['name' => 'Lavage', 'is_active' => '1']);
    expect($wash->fresh()->name)->toBe('Lavage');

    $this->actingAs($this->accountant)->delete(route('admin.expenses.categories.destroy', $wash))->assertSessionHas('success');
    expect(ExpenseCategory::query()->find($wash->id))->toBeNull();

    Expense::factory()->create(['expense_category_id' => $this->fuel->id]);
    $this->actingAs($this->accountant)->delete(route('admin.expenses.categories.destroy', $this->fuel))->assertSessionHas('warning');
    expect($this->fuel->fresh()->is_active)->toBeFalse();

    // An archived type is no longer offered.
    $this->actingAs($this->accountant)->post(route('admin.expenses.store'), [
        'spent_on' => '2026-10-07', 'expense_category_id' => $this->fuel->id, 'label' => 'Gasoil', 'amount' => '1000', 'payment_method' => 'cash',
    ])->assertSessionHasErrors('expense_category_id');
});

// -- Dashboard ---------------------------------------------------------------------------------

it('adds the expenses and the net result to the dashboard', function () {
    Payment::factory()->monthly()->create(['status' => PaymentStatus::Succeeded, 'receipt_number' => 'BA-2026-0001', 'amount' => 100000, 'list_amount' => 100000, 'confirmed_at' => '2026-10-03 08:00']);
    Expense::factory()->create(['expense_category_id' => $this->fuel->id, 'amount' => 40000, 'spent_on' => '2026-10-02']);
    Expense::factory()->create(['expense_category_id' => $this->fuel->id, 'amount' => 5000, 'spent_on' => '2026-09-02']);

    $stats = new DashboardStats(Carbon::parse('2026-10-01'));
    expect($stats->kpis())->expenses->toBe(40000)->previous_expenses->toBe(5000)->net->toBe(60000)
        ->and($stats->expensesByCategory())->toBe(['Carburant' => 40000])
        ->and($stats->monthly()['expenses'][11])->toBe(40000);

    $this->actingAs(userWithRole(RoleName::Gestionnaire))->get(route('admin.dashboard'))
        ->assertSee('Dépenses du mois')->assertSee("40\u{00A0}000\u{00A0}FCFA")
        ->assertSee('Résultat (chiffre d’affaires − dépenses)')->assertSee("60\u{00A0}000\u{00A0}FCFA");
});

// -- Rights ------------------------------------------------------------------------------------

it('keeps expenses away from desk, control and customers, and read-only for direction', function () {
    $expense = Expense::factory()->create(['expense_category_id' => $this->fuel->id]);

    foreach ([RoleName::Guichetier, RoleName::Controleur, RoleName::Client] as $role) {
        $this->actingAs(userWithRole($role))->get(route('admin.expenses.index'))->assertForbidden();
        $this->actingAs(userWithRole($role))->get(route('admin.expenses.attachment', $expense))->assertForbidden();
    }

    $direction = userWithRole(RoleName::Direction);
    $this->actingAs($direction)->delete(route('admin.expenses.destroy', $expense))->assertForbidden();
    $this->actingAs($direction)->post(route('admin.expenses.categories.store'), ['name' => 'X'])->assertForbidden();
    expect($expense->fresh())->not->toBeNull();
});
