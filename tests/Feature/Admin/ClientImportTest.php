<?php

use App\Enums\RoleName;
use App\Models\Subscriber;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->stops = networkSetUp()['stops'];
    $this->manager = userWithRole(RoleName::Gestionnaire);
});

/**
 * @param  list<list<mixed>>  $rows
 */
function clientXlsx(array $rows, string $name = 'clients.xlsx'): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1', true);
    $path = tempnam(sys_get_temp_dir(), 'clients').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, $name, null, null, true);
}

function previewImport(UploadedFile $file): TestResponse
{
    return test()->actingAs(test()->manager)->post(route('admin.subscribers.import.preview'), ['file' => $file]);
}

it('previews the list, then adds only the new clients once confirmed', function () {
    Subscriber::factory()->create(['full_name' => 'BROU NESTOR', 'phone' => '+2250787661418']);

    previewImport(clientXlsx([
        ['Nom et prénom', 'Téléphone', 'Point de montée'],
        ['KOUASSI RODOLPHE', '07 08 26 44 21', 'Zouglou'],
        ['Mme DIBY REGINA', '0709494414', ''],
        ['Mme KOFFI AMAN', '07.09.49.44.14', 'marcory'],   // same phone as Diby: allowed
        ['brou nestor', '07 87 66 14 18', null],          // already known
        ['KOUASSI RODOLPHE', '0708264421', 'Zouglou'],    // twice in the file
        ['', '0701010101', ''],                           // no name
        ['YAO PAUL', '12345', ''],                        // bad phone
        ['AKA OLIVIER', '0701020304', 'Treichville'],     // unknown stop
        [null, null, null],                               // empty line ignored
    ]))
        ->assertOk()
        ->assertSee('Ajouter 3 client(s)')
        ->assertSee('Déjà enregistré (même nom et même numéro).')
        ->assertSee('En double dans le fichier (ligne 2).')
        ->assertSee('Nom manquant.')
        ->assertSee('Numéro « 12345 » invalide')
        ->assertSee('Point de montée « Treichville » inconnu');

    expect(Subscriber::query()->count())->toBe(1); // nothing written at preview

    $token = session('client_import')['token'];
    $this->actingAs($this->manager)->post(route('admin.subscribers.import.store'), ['token' => $token])
        ->assertRedirect(route('admin.subscribers.index'))
        ->assertSessionHas('success', '3 client(s) ajouté(s) depuis « clients.xlsx ». Pensez à prendre leur photo à leur prochain passage.');

    expect(Subscriber::query()->where('full_name', 'KOUASSI RODOLPHE')->sole())
        ->phone->toBe('+2250708264421')
        ->stop_id->toBe($this->stops['Zouglou']->id)
        ->and(Subscriber::query()->where('full_name', 'Mme KOFFI AMAN')->sole()->stop_id)->toBe($this->stops['Marcory']->id)
        ->and(Subscriber::query()->where('phone', '+2250709494414')->count())->toBe(2)
        ->and(Subscriber::query()->count())->toBe(4)
        ->and(Activity::query()->where('event', 'subscribers.imported')->sole()->properties['created'])->toBe(3);
});

it('reads a list without headings (A = name, B = phone) and phones turned into numbers by Excel', function () {
    previewImport(clientXlsx([['KONE AWA', 708264421], ['TRAORE ALI', '+225 05 44 33 22 11']]))->assertSee('Ajouter 2 client(s)');
    $this->actingAs($this->manager)->post(route('admin.subscribers.import.store'), ['token' => session('client_import')['token']]);

    expect(Subscriber::query()->pluck('phone', 'full_name')->all())->toBe(['KONE AWA' => '+2250708264421', 'TRAORE ALI' => '+2250544332211']);
});

it('finds the columns by their heading, in any order and spelling', function () {
    previewImport(clientXlsx([
        ['Liste des abonnés – octobre'],
        ['N°', 'CONTACT', 'NOMS ET PRÉNOMS'],
        [1, '0707070707', 'SORO MAMADOU'],
    ]))->assertSee('Ajouter 1 client(s)')->assertSee('SORO MAMADOU')->assertSee('07 07 07 07 07');
});

it('reads a CSV saved by Excel (semicolons, Windows-1252)', function () {
    $path = tempnam(sys_get_temp_dir(), 'csv').'.csv';
    file_put_contents($path, mb_convert_encoding("Nom;Téléphone\r\nKOUAMÉ ÉLODIE;07 08 09 10 11\r\n", 'Windows-1252', 'UTF-8'));

    previewImport(new UploadedFile($path, 'clients.csv', 'text/csv', null, true))->assertSee('KOUAMÉ ÉLODIE')->assertSee('Ajouter 1 client(s)');
});

it('refuses an unreadable or empty file, and an expired confirmation', function () {
    previewImport(UploadedFile::fake()->create('photo.pdf', 10, 'application/pdf'))->assertSessionHasErrors('file');
    previewImport(clientXlsx([['Nom', 'Téléphone']]))->assertSessionHasErrors(['file' => 'Aucun client trouvé dans ce fichier (colonnes attendues : Nom, Téléphone).']);

    $this->actingAs($this->manager)->post(route('admin.subscribers.import.store'), ['token' => 'forged'])->assertStatus(419);
    expect(Subscriber::query()->count())->toBe(0);
});

it('never imports twice the same confirmation', function () {
    previewImport(clientXlsx([['Nom', 'Téléphone'], ['KONE AWA', '0708264421']]));
    $token = session('client_import')['token'];

    $this->actingAs($this->manager)->post(route('admin.subscribers.import.store'), ['token' => $token]);
    $this->actingAs($this->manager)->post(route('admin.subscribers.import.store'), ['token' => $token])->assertStatus(419);

    expect(Subscriber::query()->count())->toBe(1);
});

it('offers an Excel template to fill', function () {
    $response = $this->actingAs($this->manager)->get(route('admin.subscribers.import.template'));

    $response->assertOk()->assertDownload('modele-liste-clients.xlsx');
});

it('is reserved to people who can create clients', function (RoleName $role) {
    $this->actingAs(userWithRole($role))->get(route('admin.subscribers.import'))->assertForbidden();
    $this->actingAs(userWithRole($role))->post(route('admin.subscribers.import.preview'), ['file' => clientXlsx([['A', '0708264421']])])->assertForbidden();
})->with([RoleName::Comptable, RoleName::Direction, RoleName::Client, RoleName::Controleur]);
