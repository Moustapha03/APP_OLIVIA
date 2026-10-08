<?php

use App\Enums\PassStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\Payment;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Services\Photos\SubscriberPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Carbon::setTestNow('2026-10-06 10:00:00');
    Storage::fake(SubscriberPhoto::DISK);
    $this->stops = networkSetUp()['stops'];
    $this->manager = userWithRole(RoleName::Gestionnaire);
});

// -- Create, edit -----------------------------------------------------------------------------

it('creates a client from the back office, then shows their file', function () {
    $this->actingAs($this->manager)->post(route('admin.subscribers.store'), [
        'full_name' => 'KOUASSI RODOLPHE', 'phone' => '07 08 26 44 21', 'stop_id' => $this->stops['Zouglou']->id,
        'photo' => UploadedFile::fake()->image('visage.jpg', 600, 800),
    ])->assertRedirect(route('admin.subscribers.show', $subscriber = Subscriber::query()->sole()));

    $this->actingAs($this->manager)->get(route('admin.subscribers.show', $subscriber))
        ->assertOk()->assertSee('KOUASSI RODOLPHE')->assertSee('07 08 26 44 21')->assertSee('Zouglou')->assertSee('Bonoua / Bassam');
});

it('edits a client', function () {
    $subscriber = Subscriber::factory()->create(['stop_id' => $this->stops['Anani']->id]);

    $this->actingAs($this->manager)->put(route('admin.subscribers.update', $subscriber), [
        'full_name' => 'Mme DIBY REGINA', 'phone' => '0709494414', 'stop_id' => $this->stops['Marcory']->id,
    ])->assertRedirect(route('admin.subscribers.show', $subscriber));

    expect($subscriber->fresh())->full_name->toBe('Mme DIBY REGINA')->stop_id->toBe($this->stops['Marcory']->id);
});

// -- Delete -----------------------------------------------------------------------------------

it('deletes a client who never paid, with the photo', function () {
    $subscriber = Subscriber::factory()->create();
    Storage::disk(SubscriberPhoto::DISK)->put($subscriber->photo_path, 'jpeg');

    $this->actingAs($this->manager)->delete(route('admin.subscribers.destroy', $subscriber))->assertRedirect(route('admin.subscribers.index'));

    expect(Subscriber::withTrashed()->count())->toBe(0);
    Storage::disk(SubscriberPhoto::DISK)->assertMissing($subscriber->photo_path);
});

it('archives a client who paid: receipts keep the name, the photo is erased', function () {
    $subscriber = Subscriber::factory()->create(['full_name' => 'COULIBALY KADY']);
    $path = $subscriber->photo_path;
    Storage::disk(SubscriberPhoto::DISK)->put($path, 'jpeg');
    $old = Subscription::factory()->for($subscriber)->create(['starts_on' => '2026-08-01', 'ends_on' => '2026-08-31']);
    $payment = Payment::factory()->monthly()->create(['subscriber_id' => $subscriber->id, 'status' => PaymentStatus::Succeeded, 'receipt_number' => 'BA-2026-0008']);

    $this->actingAs($this->manager)->delete(route('admin.subscribers.destroy', $subscriber))->assertSessionHas('success');

    $archived = Subscriber::withTrashed()->find($subscriber->id);
    expect(Subscriber::query()->count())->toBe(0)
        ->and($archived->trashed())->toBeTrue()
        ->and($archived->photo_path)->toBeNull()
        ->and($payment->fresh()->subscriber->full_name)->toBe('COULIBALY KADY')
        ->and($old->fresh()->subscriber->full_name)->toBe('COULIBALY KADY');
    Storage::disk(SubscriberPhoto::DISK)->assertMissing($path);

    $this->actingAs($this->manager)->get(route('admin.subscribers.index', ['statut' => 'archive']))->assertSee('COULIBALY KADY');
    $this->actingAs($this->manager)->get(route('admin.subscribers.show', $subscriber->id))->assertOk()->assertSee('Client supprimé le 06/10/2026');
});

it('refuses to delete a client whose subscription is running', function () {
    $subscriber = Subscriber::factory()->create();
    Subscription::factory()->for($subscriber)->create(['status' => PassStatus::Active]);

    $this->actingAs($this->manager)->from(route('admin.subscribers.edit', $subscriber))
        ->delete(route('admin.subscribers.destroy', $subscriber))
        ->assertSessionHasErrors(['subscriber' => $subscriber->full_name.' a un abonnement valable jusqu’au 31 octobre 2026 : impossible de le supprimer avant cette date.']);

    expect($subscriber->fresh()->trashed())->toBeFalse();
});

it('lets only managers delete clients', function (RoleName $role) {
    $subscriber = Subscriber::factory()->create();

    $this->actingAs(userWithRole($role))->delete(route('admin.subscribers.destroy', $subscriber))->assertForbidden();
    expect($subscriber->fresh())->not->toBeNull();
})->with([RoleName::Guichetier, RoleName::Comptable, RoleName::Direction, RoleName::Client]);

// -- List: search and filters -------------------------------------------------------------------

it('searches clients by name, phone or receipt number, and filters them', function () {
    $brou = Subscriber::factory()->create(['full_name' => 'BROU NESTOR', 'phone' => '+2250787661418', 'stop_id' => $this->stops['Zouglou']->id]);
    $koffi = Subscriber::factory()->create(['full_name' => 'Mme KOFFI AMAN', 'phone' => '+2250709494414', 'stop_id' => $this->stops['Marcory']->id, 'photo_path' => null]);
    Subscription::factory()->for($brou)->create();
    Payment::factory()->create(['subscriber_id' => $koffi->id, 'receipt_number' => 'BA-2026-0011']);

    $get = fn (array $query) => $this->actingAs($this->manager)->get(route('admin.subscribers.index', $query));

    $get(['q' => 'nestor brou'])->assertSee('BROU NESTOR')->assertDontSee('KOFFI');
    $get(['q' => '07 09 49 44 14'])->assertSee('KOFFI')->assertDontSee('BROU');
    $get(['q' => '0011'])->assertSee('KOFFI')->assertDontSee('BROU');
    $get(['zone' => $this->stops['Marcory']->zone_id])->assertSee('KOFFI')->assertDontSee('BROU');
    $get(['statut' => 'abonne'])->assertSee('BROU')->assertDontSee('KOFFI');
    $get(['statut' => 'sans'])->assertSee('KOFFI')->assertDontSee('BROU');
    $get(['statut' => 'photo'])->assertSee('KOFFI')->assertDontSee('BROU');
});

it('lists the payment history in the client file, with a link to each transaction', function () {
    $subscriber = Subscriber::factory()->create();
    $paid = Payment::factory()->monthly()->create(['subscriber_id' => $subscriber->id, 'status' => PaymentStatus::Succeeded, 'receipt_number' => 'BA-2026-0042']);
    Payment::factory()->monthly()->create(['subscriber_id' => $subscriber->id, 'status' => PaymentStatus::Cancelled, 'receipt_number' => 'BA-2026-0043']);

    $this->actingAs($this->manager)->get(route('admin.subscribers.show', $subscriber))
        ->assertSee('BA-2026-0042')->assertSee('BA-2026-0043')->assertSee('Annulé')
        ->assertSee(route('admin.receipts.show', $paid->reference));
});

it('offers edit and delete straight from the client list (not for archived clients)', function () {
    $active = Subscriber::factory()->create(['full_name' => 'KONE AWA']);
    $archived = Subscriber::factory()->create(['full_name' => 'YAO PAUL']);
    $archived->delete();

    $this->actingAs($this->manager)->get(route('admin.subscribers.index'))
        ->assertSee(route('admin.subscribers.edit', $active))
        ->assertSee('data-modal-open="delete-subscriber-'.$active->id.'"', false)
        ->assertSee(route('admin.subscribers.destroy', $active));

    $this->actingAs($this->manager)->get(route('admin.subscribers.index', ['statut' => 'archive']))
        ->assertSee('YAO PAUL')
        ->assertDontSee(route('admin.subscribers.edit', $archived));

    // The accountant sees the list without the delete button (no customers.delete).
    $this->actingAs(userWithRole(RoleName::Comptable))->get(route('admin.subscribers.index'))
        ->assertDontSee('data-modal-open="delete-subscriber-'.$active->id.'"', false);
});

it('explains why a client with a running subscription cannot be deleted from the list', function () {
    $subscriber = Subscriber::factory()->create(['full_name' => 'KONE AWA']);
    Subscription::factory()->for($subscriber)->create(['status' => PassStatus::Active]);

    $this->actingAs($this->manager)->from(route('admin.subscribers.index'))
        ->followingRedirects()
        ->delete(route('admin.subscribers.destroy', $subscriber))
        ->assertSee('KONE AWA a un abonnement valable jusqu’au 31 octobre 2026');
});
