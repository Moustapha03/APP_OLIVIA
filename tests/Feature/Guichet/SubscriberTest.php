<?php

use App\Actions\Scans\VerifyPass;
use App\Enums\RoleName;
use App\Models\CashSession;
use App\Models\Subscriber;
use App\Services\Photos\SubscriberPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(SubscriberPhoto::DISK);
    $this->stops = networkSetUp()['stops'];
    $this->agent = userWithRole(RoleName::Guichetier);
});

it('creates a subscriber with photo and boarding point', function () {
    $this->actingAs($this->agent)->post(route('guichet.subscribers.store'), [
        'full_name' => '  Mme  KOFFI   AMAN ',
        'phone' => '07 09  49  44 14',
        'stop_id' => $this->stops['Marcory']->id,
        'photo' => UploadedFile::fake()->image('visage.jpg', 600, 800),
    ])->assertRedirect();

    $subscriber = Subscriber::query()->sole();
    expect($subscriber)
        ->full_name->toBe('Mme KOFFI AMAN')
        ->phone->toBe('+2250709494414')
        ->stop_id->toBe($this->stops['Marcory']->id)
        ->user_id->toBeNull()
        ->and($subscriber->hasPhoto())->toBeTrue();
});

it('allows several subscribers on the same phone (a relative pays)', function () {
    foreach (['Mme DIBY REGINA', 'Mme KOFFI AMAN'] as $name) {
        $this->actingAs($this->agent)->post(route('guichet.subscribers.store'), [
            'full_name' => $name, 'phone' => '0709494414', 'stop_id' => $this->stops['Anani']->id,
        ])->assertSessionHasNoErrors();
    }

    expect(Subscriber::query()->where('phone', '+2250709494414')->count())->toBe(2);
});

it('goes straight to the subscription sale after creating', function () {
    CashSession::factory()->for($this->agent)->create();

    $this->actingAs($this->agent)->post(route('guichet.subscribers.store'), [
        'full_name' => 'Awa', 'stop_id' => $this->stops['Zouglou']->id, 'then' => 'subscription',
    ])->assertRedirect(route('guichet.subscriptions.create', ['abonne' => Subscriber::query()->sole()->id]));
});

it('links a subscriber to the customer account of the same phone only when asked', function () {
    $account = userWithRole(RoleName::Client, ['phone' => '+2250712345678']);
    $staff = userWithRole(RoleName::Controleur, ['phone' => '+2250712345679']);

    $this->actingAs($this->agent)->post(route('guichet.subscribers.store'), ['full_name' => 'Awa A', 'phone' => '0712345678', 'stop_id' => $this->stops['Anani']->id]);
    expect(Subscriber::query()->latest('id')->first()->user_id)->toBeNull();

    $this->actingAs($this->agent)->post(route('guichet.subscribers.store'), ['full_name' => 'Bia B', 'phone' => '0712345678', 'stop_id' => $this->stops['Anani']->id, 'link_account' => '1']);
    expect(Subscriber::query()->latest('id')->first()->user_id)->toBe($account->id);

    // Never to a staff account.
    $this->actingAs($this->agent)->post(route('guichet.subscribers.store'), ['full_name' => 'Cyr C', 'phone' => '0712345679', 'stop_id' => $this->stops['Anani']->id, 'link_account' => '1']);
    expect(Subscriber::query()->latest('id')->first()->user_id)->toBeNull();
});

it('finds subscribers by name words or phone, never everybody', function () {
    Subscriber::factory()->create(['full_name' => 'Sarah Tiphanie Ouattara', 'phone' => '+2250789255244']);
    Subscriber::factory()->create(['full_name' => 'KOUASSI RODOLPHE', 'phone' => '+2250708264421']);

    $this->actingAs($this->agent)->get(route('guichet.subscribers.index', ['q' => 'sarah ouattara']))->assertSee('Sarah Tiphanie Ouattara')->assertDontSee('RODOLPHE');
    $this->actingAs($this->agent)->get(route('guichet.subscribers.index', ['q' => '07 08 26 44 21']))->assertSee('RODOLPHE')->assertDontSee('Sarah');
    $this->actingAs($this->agent)->get(route('guichet.subscribers.index', ['q' => '12']))->assertDontSee('RODOLPHE')->assertDontSee('Sarah');
});

it('validates the subscriber', function () {
    $this->actingAs($this->agent)->post(route('guichet.subscribers.store'), ['full_name' => '', 'phone' => '0812345678', 'stop_id' => 999999])
        ->assertSessionHasErrors(['full_name', 'phone', 'stop_id']);
});

it('shows the photo to agents, to controllers through the signed link of a scan only, not to other customers', function () {
    $subscriber = Subscriber::factory()->create();
    Storage::disk(SubscriberPhoto::DISK)->put($subscriber->photo_path, 'jpeg');
    $controller = userWithRole(RoleName::Controleur);

    $this->actingAs($this->agent)->get(route('photos.subscriber', $subscriber))->assertOk();
    $this->actingAs($controller)->get(route('photos.subscriber', $subscriber))->assertForbidden();
    $this->actingAs($controller)->get(VerifyPass::photoUrl($subscriber->id))->assertOk();
    $this->actingAs(userWithRole(RoleName::Client))->get(route('photos.subscriber', $subscriber))->assertForbidden();
    $this->actingAs(userWithRole(RoleName::Comptable))->get(route('photos.subscriber', $subscriber))->assertForbidden();
    $this->actingAs(userWithRole(RoleName::Gestionnaire))->get(route('photos.subscriber', $subscriber))->assertOk();
});
