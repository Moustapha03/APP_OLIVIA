<?php

use App\Enums\RoleName;
use App\Models\Payment;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Services\Photos\SubscriberPhoto;
use App\Services\Qr\PassSigner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->stops = networkSetUp()['stops'];
    $this->owner = userWithRole(RoleName::Client);
    $this->subscriber = Subscriber::factory()->create(['user_id' => $this->owner->id, 'full_name' => 'Awa Koné', 'stop_id' => $this->stops['Zouglou']->id]);
    $this->ticket = Ticket::factory()->for($this->owner)->create(['zone_id' => $this->stops['Anani']->zone_id]);
    $this->subscription = Subscription::factory()->for($this->subscriber)->create();
});

it('lists the account tickets and the subscriptions of its subscribers', function () {
    $this->actingAs($this->owner)->get(route('espace.passes.index'))
        ->assertOk()->assertSee('À utiliser')->assertSee('Awa Koné')->assertSee('Ticket du jour');

    $this->actingAs($this->owner)->get(route('espace.home'))->assertOk()->assertSee('Mes titres valables');
});

it('shows a subscription bought at the agency to the linked account', function () {
    // Sold at the desk (user_id null), subscriber linked to the account by the agency.
    expect($this->subscription->user_id)->toBeNull();

    $this->actingAs($this->owner)->get(route('espace.subscriptions.show', $this->subscription->uuid))
        ->assertOk()->assertSee('Awa Koné')->assertSee('QR code de l’abonnement', false);
});

it('shows a QR code that verifies, without personal data', function () {
    $payload = app(PassSigner::class)->sign($this->subscription);

    expect(app(PassSigner::class)->verify($payload)?->find()?->is($this->subscription))->toBeTrue()
        ->and($payload)->not->toContain('Awa')
        ->and($payload)->not->toContain((string) $this->subscriber->phone);
});

it('never shows another customer the passes, payments or subscriber photos (IDOR)', function () {
    $intruder = userWithRole(RoleName::Client);
    $payment = Payment::factory()->for($this->owner)->create();

    $this->actingAs($intruder)->get(route('espace.tickets.show', $this->ticket->uuid))->assertForbidden();
    $this->actingAs($intruder)->get(route('espace.tickets.pdf', $this->ticket->uuid))->assertForbidden();
    $this->actingAs($intruder)->get(route('espace.subscriptions.show', $this->subscription->uuid))->assertForbidden();
    $this->actingAs($intruder)->get(route('espace.subscriptions.pdf', $this->subscription->uuid))->assertForbidden();
    $this->actingAs($intruder)->get(route('espace.payments.show', $payment->reference))->assertForbidden();
    $this->actingAs($intruder)->get(route('photos.subscriber', $this->subscriber))->assertForbidden();
    $this->actingAs($intruder)->put(route('espace.subscribers.update', $this->subscriber), ['full_name' => 'X', 'stop_id' => $this->stops['Anani']->id])->assertForbidden();

    $this->actingAs($intruder)->get(route('espace.passes.index'))->assertDontSee('Awa Koné');
});

it('downloads the pass as a PDF', function (string $route, string $param) {
    $response = $this->actingAs($this->owner)->get(route($route, $this->{$param}->uuid));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF');
})->with([
    'ticket' => ['espace.tickets.pdf', 'ticket'],
    'subscription' => ['espace.subscriptions.pdf', 'subscription'],
]);

it('hides the QR of a used up ticket', function () {
    $this->ticket->forceFill(['rides_used' => 2])->save();

    $this->actingAs($this->owner)->get(route('espace.tickets.show', $this->ticket->uuid))
        ->assertOk()->assertSee('Utilisé')->assertDontSee('QR code du ticket');
});

// -- My subscribers -----------------------------------------------------------------

it('adds a subscriber with photo to the account', function () {
    Storage::fake(SubscriberPhoto::DISK);

    $this->actingAs($this->owner)->post(route('espace.subscribers.store'), [
        'full_name' => 'Yao Koné', 'phone' => '0712345678', 'stop_id' => $this->stops['Bonoua']->id,
        'photo' => UploadedFile::fake()->image('yao.jpg', 600, 800),
    ])->assertRedirect(route('espace.subscribers.index'));

    $subscriber = Subscriber::query()->where('full_name', 'Yao Koné')->sole();
    expect($subscriber->user_id)->toBe($this->owner->id)->and($subscriber->hasPhoto())->toBeTrue();
    Storage::disk(SubscriberPhoto::DISK)->assertExists($subscriber->photo_path);
});

it('requires a real photo to add a subscriber', function (?UploadedFile $photo) {
    $this->actingAs($this->owner)->post(route('espace.subscribers.store'), [
        'full_name' => 'Yao', 'stop_id' => $this->stops['Bonoua']->id, 'photo' => $photo,
    ])->assertSessionHasErrors('photo');
})->with([
    'none' => [null],
    'pdf' => [fn () => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf')],
    'php disguised as jpg' => [fn () => UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo 1;')],
    'too small' => [fn () => UploadedFile::fake()->image('tiny.jpg', 100, 100)],
]);

it('stores the photo privately, re-encoded (no metadata), under a random name', function () {
    Storage::fake(SubscriberPhoto::DISK);

    $this->actingAs($this->owner)->post(route('espace.subscribers.store'), [
        'full_name' => 'Yao', 'stop_id' => $this->stops['Bonoua']->id, 'photo' => UploadedFile::fake()->image('moi.png', 1200, 1600),
    ]);

    $path = Subscriber::query()->where('full_name', 'Yao')->sole()->photo_path;
    expect($path)->toMatch('#^photos/[0-9a-f-]{36}\.jpg$#');
    [$width, $height, $type] = getimagesizefromstring(Storage::disk(SubscriberPhoto::DISK)->get($path));
    expect($type)->toBe(IMAGETYPE_JPEG)->and(max($width, $height))->toBe(800);

    $this->get('/storage/'.$path)->assertNotFound();
});
