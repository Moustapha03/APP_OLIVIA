<?php

use App\Enums\FareType;
use App\Enums\RoleName;
use App\Models\Payment;
use App\Models\Stop;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Services\Receipts\ReceiptNumbers;
use Illuminate\Support\Carbon;

const CLIENT_CSV = <<<'CSV'
"N°";"Date";"Abonné";"Téléphone";"Point de montée";"Mois";"Formule";"Paiement";"Réf.";"Montant FCFA"
"BA-2026-0017";"05/10/2026";"BROU NESTOR";"07 87 66 14 18";"ZOUGLOU";"octobre 2026";"Soir simple";"Wave";"";"30000"
"BA-2026-0012";"02/10/2026";"Mme DIBY REGINA";"0709494414";"CARREFOUR AEROPORT";"octobre 2026";"Matin simple";"Espèces";"";"30000"
"BA-2026-0011";"02/10/2026";"Mme KOFFI AMAN";"07 09  49  44 14";"Marcory";"octobre 2026";"Matin & soir";"Espèces";"";"25000"
"BA-2026-0001";"28/09/2026";"KIESSE KETSIA";"07-79-31-91-50";"CARREFOUR HÔPITAL ZOUGLOU";"octobre 2026";"Matin simple";"Espèces";"";"30000"
"";"";"";"";"";"";"";"";"TOTAL";"115000"
CSV;

function clientCsv(string $content = CLIENT_CSV): string
{
    $path = tempnam(sys_get_temp_dir(), 'recus').'.csv';
    file_put_contents($path, $content);

    return $path;
}

beforeEach(function () {
    Carbon::setTestNow('2026-10-06 10:00:00');
    $this->network = networkSetUp();
    // A boarding point of the history that the test network lacks, added like an admin would.
    $this->network['stops']['Carrefour Hôpital Zouglou'] = Stop::factory()->for($this->network['bassam'])->create(['name' => 'Carrefour Hôpital Zouglou']);
});

// -- Import of the agency's history --------------------------------------------------------

it('imports the agency history: subscribers, paid receipts with their numbers, subscriptions', function () {
    $this->artisan('receipts:import', ['file' => clientCsv()])->assertSuccessful();

    expect(Payment::query()->count())->toBe(4)
        ->and(Payment::query()->sum('amount'))->toEqual(115000)
        ->and(Subscription::query()->count())->toBe(4)
        ->and(Payment::query()->pluck('receipt_number')->sort()->values()->all())->toBe(['BA-2026-0001', 'BA-2026-0011', 'BA-2026-0012', 'BA-2026-0017']);

    $brou = Subscription::query()->whereHas('subscriber', fn ($q) => $q->where('full_name', 'BROU NESTOR'))->sole();
    expect($brou->formula)->toBe(FareType::MonthlyEvening)
        ->and($brou->starts_on->toDateString())->toBe('2026-10-01')
        ->and($brou->payment->payment_method->label())->toBe('Wave');

    // Same phone, two people.
    expect(Subscriber::query()->where('phone', '+2250709494414')->count())->toBe(2);

    // Marcory "Matin & soir" paid 25 000 instead of 30 000: kept, flagged.
    $koffi = Payment::query()->where('receipt_number', 'BA-2026-0011')->sole();
    expect($koffi->amount)->toBe(25000)->and($koffi->list_amount)->toBe(30000)->and($koffi->isDiscounted())->toBeTrue();

    // Numbering continues after the history.
    expect(app(ReceiptNumbers::class)->next(FareType::Monthly))->toBe('BA-2026-0018');
});

it('is idempotent: importing twice adds nothing', function () {
    $this->artisan('receipts:import', ['file' => clientCsv()])->assertSuccessful();
    $this->artisan('receipts:import', ['file' => clientCsv()])->assertSuccessful()->expectsOutputToContain('0 reçu(s) à importer, 4 déjà présent(s).');

    expect(Payment::query()->count())->toBe(4);
});

it('writes nothing in dry-run mode', function () {
    $this->artisan('receipts:import', ['file' => clientCsv(), '--dry-run' => true])->assertSuccessful()->expectsOutputToContain('Mode essai');

    expect(Payment::query()->count())->toBe(0);
});

it('refuses the whole file when a boarding point is unknown', function () {
    $csv = str_replace('ZOUGLOU', 'TREICHVILLE', CLIENT_CSV);

    $this->artisan('receipts:import', ['file' => clientCsv($csv)])->assertFailed()->expectsOutputToContain('point de montée inconnu « TREICHVILLE »');

    expect(Payment::query()->count())->toBe(0);
});

it('reads a Windows-1252 file exported by Excel', function () {
    $this->artisan('receipts:import', ['file' => clientCsv(mb_convert_encoding(CLIENT_CSV, 'Windows-1252', 'UTF-8'))])->assertSuccessful();

    expect(Payment::query()->count())->toBe(4);
});

// -- Receipts list and export -----------------------------------------------------------

it('lists the receipts of the month with totals by payment method', function () {
    $this->artisan('receipts:import', ['file' => clientCsv()]);

    $this->actingAs(userWithRole(RoleName::Comptable))->get(route('admin.receipts.index'))
        ->assertOk()
        ->assertSee('BA-2026-0017')
        ->assertSee('BROU NESTOR')
        ->assertSee('Soir simple')
        ->assertSee("115\u{00A0}000\u{00A0}FCFA")
        ->assertSee('grille');
});

it('exports the CSV in the agency format, with the TOTAL line', function () {
    $this->artisan('receipts:import', ['file' => clientCsv()]);

    $response = $this->actingAs(userWithRole(RoleName::Gestionnaire))->get(route('admin.receipts.export', ['month' => '2026-10']));
    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $csv = $response->streamedContent();

    $lines = preg_split('/\r\n/', trim(preg_replace('/^\xEF\xBB\xBF/', '', $csv)));
    expect($lines[0])->toBe('"N°";"Date";"Abonné";"Téléphone";"Point de montée";"Mois";"Formule";"Paiement";"Réf.";"Montant FCFA"')
        ->and($csv)->toContain('"BA-2026-0017";"05/10/2026";"BROU NESTOR";"07 87 66 14 18";"Zouglou";"octobre 2026";"Soir simple";"Wave";"";"30000"')
        ->and(end($lines))->toBe('"";"";"";"";"";"";"";"";"TOTAL";"115000"');
});

it('keeps receipts away from desk and control staff', function (RoleName $role) {
    $this->actingAs(userWithRole($role))->get(route('admin.receipts.index'))->assertForbidden();
    $this->actingAs(userWithRole($role))->get(route('admin.receipts.export'))->assertForbidden();
})->with([RoleName::Guichetier, RoleName::Controleur, RoleName::Client]);

// -- Transaction history and detail ---------------------------------------------------------

it('shows a transaction with its QR code and reprints the receipt', function () {
    $this->artisan('receipts:import', ['file' => clientCsv()]);
    $payment = Payment::query()->where('receipt_number', 'BA-2026-0017')->sole();

    $this->actingAs(userWithRole(RoleName::Gestionnaire))->get(route('admin.receipts.show', $payment))
        ->assertOk()->assertSee('Reçu N° BA-2026-0017')->assertSee('BROU NESTOR')->assertSee('Historique importé')->assertSee('QR code du reçu BA-2026-0017');

    $this->actingAs(userWithRole(RoleName::Comptable))->get(route('admin.receipts.print', $payment))
        ->assertOk()->assertSee('Reçu N° BA-2026-0017')->assertSee('QR code du titre');
});

it('filters the history by status and zone, and searches by receipt number', function () {
    $this->artisan('receipts:import', ['file' => clientCsv()]);
    Payment::query()->where('receipt_number', 'BA-2026-0012')->update(['status' => 'cancelled']);
    $user = userWithRole(RoleName::Comptable);

    $this->actingAs($user)->get(route('admin.receipts.index', ['status' => 'cancelled']))->assertSee('BA-2026-0012')->assertDontSee('BA-2026-0017');
    $this->actingAs($user)->get(route('admin.receipts.index'))->assertDontSee('BA-2026-0012')->assertSee('BA-2026-0017');
    $this->actingAs($user)->get(route('admin.receipts.index', ['zone' => $this->network['marcory']->id]))->assertSee('BA-2026-0011')->assertDontSee('BA-2026-0017');
    $this->actingAs($user)->get(route('admin.receipts.index', ['q' => '0017']))->assertSee('BROU NESTOR')->assertDontSee('KIESSE');
});

it('never exports cancelled receipts, whatever the filter', function () {
    $this->artisan('receipts:import', ['file' => clientCsv()]);
    Payment::query()->where('receipt_number', 'BA-2026-0012')->update(['status' => 'cancelled']);

    $csv = $this->actingAs(userWithRole(RoleName::Gestionnaire))->get(route('admin.receipts.export', ['month' => '2026-10', 'status' => 'all']))->streamedContent();

    expect($csv)->not->toContain('BA-2026-0012')->toContain('"TOTAL";"85000"');
});
