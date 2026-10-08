<?php

use App\Enums\PassStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\Payment;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Services\Photos\SubscriberPhoto;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Carbon::setTestNow('2026-10-06 10:00:00');
    Storage::fake(SubscriberPhoto::DISK);
    $this->stops = networkSetUp()['stops'];
});

// F6 — formula injection in the CSV export
it('neutralises Excel formulas in the receipts export', function () {
    $subscriber = Subscriber::factory()->create(['full_name' => '=HYPERLINK("http://evil","x")']);
    Payment::factory()->monthly()->create([
        'subscriber_id' => $subscriber->id, 'status' => PaymentStatus::Succeeded, 'receipt_number' => 'BA-2026-0001',
        'confirmed_at' => '2026-10-02 08:00', 'period_month' => '2026-10-01', 'external_reference' => '+cmd|calc', 'gateway' => 'counter',
    ]);

    $csv = $this->actingAs(userWithRole(RoleName::Comptable))->get(route('admin.receipts.export', ['month' => '2026-10']))->streamedContent();

    expect($csv)->toContain('"\'=HYPERLINK(""http://evil"",""x"")"')
        ->toContain('"\'+cmd|calc"')
        ->not->toContain('";"=HYPERLINK');
});

// F23 — export needs reports.export
it('reserves the export to roles allowed to export', function () {
    $this->actingAs(userWithRole(RoleName::Gestionnaire))->get(route('admin.receipts.export'))->assertOk();
    $this->actingAs(userWithRole(RoleName::Comptable))->get(route('admin.receipts.export'))->assertOk();

    $user = userWithRole(RoleName::Gestionnaire);
    $user->roles()->first()->revokePermissionTo('reports.export');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($user->fresh())->get(route('admin.receipts.export'))->assertForbidden();
});

// F5 — a client cannot swap the holder of a running personal pass
it('refuses online name or photo changes while a subscription runs', function () {
    $client = userWithRole(RoleName::Client);
    $subscriber = Subscriber::factory()->create(['user_id' => $client->id, 'full_name' => 'AWA KONE', 'stop_id' => $this->stops['Zouglou']->id]);
    Subscription::factory()->for($subscriber)->create(['status' => PassStatus::Active]);

    $update = fn (array $data) => $this->actingAs($client)->put(route('espace.subscribers.update', $subscriber), [
        'full_name' => 'AWA KONE', 'phone' => '', 'stop_id' => $this->stops['Zouglou']->id, ...$data,
    ]);

    $update(['full_name' => 'PERSONNE AUTRE'])->assertSessionHas('error');
    $update(['photo' => UploadedFile::fake()->image('autre.jpg', 600, 800)])->assertSessionHas('error');
    expect($subscriber->fresh()->full_name)->toBe('AWA KONE');

    // Phone or boarding point can still change.
    $update(['stop_id' => $this->stops['Bonoua']->id])->assertSessionHas('success');
    expect($subscriber->fresh()->stop_id)->toBe($this->stops['Bonoua']->id);
});

// F17 — no external "back" link
it('ignores an external return address', function () {
    $this->actingAs(userWithRole(RoleName::Client))->get(route('espace.subscribers.index', ['retour' => 'https://evil.example']))
        ->assertOk()->assertDontSee('evil.example');
});

// F19 — decompression bombs
it('refuses huge photos', function () {
    $client = userWithRole(RoleName::Client);

    $this->actingAs($client)->post(route('espace.subscribers.store'), [
        'full_name' => 'AWA KONE', 'stop_id' => $this->stops['Zouglou']->id, 'photo' => UploadedFile::fake()->image('big.png', 7000, 7000),
    ])->assertSessionHasErrors(['photo' => 'La photo doit faire entre 200 × 200 et 6 000 × 6 000 pixels.']);
});

// F18 — zip bomb in the Excel import
it('refuses a spreadsheet that would expand too much', function () {
    $path = tempnam(sys_get_temp_dir(), 'bomb').'.xlsx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('xl/worksheets/sheet1.xml', str_repeat('A', 60 * 1024 * 1024));
    $zip->close();

    $this->actingAs(userWithRole(RoleName::Gestionnaire))
        ->post(route('admin.subscribers.import.preview'), ['file' => new UploadedFile($path, 'clients.xlsx', null, null, true)])
        ->assertSessionHasErrors('file');
});

// F20 — retention
it('anonymises the history of a deleted client and schedules the clean-ups', function () {
    $subscriber = Subscriber::factory()->create(['full_name' => 'TRAORE ALI', 'phone' => '+2250544332211']);
    $subscriber->update(['full_name' => 'TRAORE ALI B']);

    $this->actingAs(userWithRole(RoleName::Gestionnaire))->delete(route('admin.subscribers.destroy', $subscriber));

    $leftovers = Activity::query()->where('subject_type', Subscriber::class)->where('subject_id', $subscriber->id)->get();
    expect($leftovers)->not->toBeEmpty()
        ->and($leftovers->pluck('properties')->toJson())->not->toContain('TRAORE')->not->toContain('2250544332211');

    $tasks = collect(app(Schedule::class)->events())->map(fn ($e) => $e->description ?: $e->command)->implode(' | ');
    expect($tasks)->toContain('activitylog:clean')->toContain('scans:prune')->toContain('payments:forget-payload');
});
