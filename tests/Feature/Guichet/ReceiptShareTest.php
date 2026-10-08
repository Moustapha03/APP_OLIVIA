<?php

use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\Payment;
use App\Models\Subscriber;
use App\Services\Receipts\ReceiptSharing;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Carbon::setTestNow('2026-10-05 09:00:00');
    $setup = counterSetUp();
    $this->agent = $setup['agent'];
    $this->subscriber = Subscriber::factory()->create(['full_name' => 'BROU NESTOR', 'phone' => '+2250787661418', 'stop_id' => $setup['stops']['Zouglou']->id]);

    $this->actingAs($this->agent)->post(route('guichet.subscriptions.store'), [
        'subscriber_id' => $this->subscriber->id, 'formula' => 'monthly_evening', 'month' => '2026-10', 'payment_method' => 'cash',
    ]);
    $this->payment = Payment::query()->sole();
});

it('downloads the receipt as a PDF', function () {
    $this->actingAs($this->agent)->get(route('receipts.pdf', $this->payment))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload('recu-BA-2026-0001.pdf');
});

it('opens WhatsApp on the client number with the message and a signed link', function () {
    $response = $this->actingAs($this->agent)->get(route('receipts.whatsapp', $this->payment));

    $target = $response->headers->get('Location');
    expect($target)->toStartWith('https://wa.me/2250787661418?text=');

    $text = rawurldecode(substr($target, strpos($target, 'text=') + 5));
    expect($text)->toContain('Bonjour BROU NESTOR,')
        ->toContain('reçu N° BA-2026-0001 (Abonnement Soir simple – octobre 2026, 30')
        ->toContain('/recu/'.$this->payment->reference.'?expires=')
        ->and(Activity::query()->where('event', 'receipt.whatsapp')->sole()->causer_id)->toBe($this->agent->id);
});

it('lets the client open the PDF from the link without an account, until a week after the month', function () {
    $url = app(ReceiptSharing::class)->sharedUrl($this->payment);

    auth()->logout();
    $this->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');

    // Altered link, or another receipt: refused.
    expect($this->get(str_replace($this->payment->reference, 'TT0000000000000000000000000', $url))->getStatusCode())->toBeIn([403, 404]);
    $this->get($url.'x')->assertForbidden();

    Carbon::setTestNow('2026-11-08 00:00:01');
    $this->get($url)->assertForbidden();
});

it('stops the link once the sale is cancelled', function () {
    $url = app(ReceiptSharing::class)->sharedUrl($this->payment);
    $this->payment->forceFill(['status' => PaymentStatus::Cancelled])->save();

    $this->get($url)->assertStatus(410);
    $this->actingAs($this->agent)->get(route('receipts.whatsapp', $this->payment))->assertNotFound();
});

it('shows the buttons on the printed receipt and the back-office page', function () {
    $this->actingAs($this->agent)->get(route('guichet.sales.receipt', $this->payment->reference))
        ->assertSee('Télécharger le reçu (PDF)')
        ->assertSee('Envoyer par WhatsApp au 07 87 66 14 18')
        ->assertSee(route('receipts.whatsapp', $this->payment->reference));

    $this->actingAs(userWithRole(RoleName::Gestionnaire))->get(route('admin.receipts.show', $this->payment))
        ->assertSee('Télécharger le reçu (PDF)')->assertSee('Envoyer par WhatsApp');
});

it('never gives a receipt to another agent or customer (IDOR)', function (RoleName $role) {
    $other = userWithRole($role);

    $this->actingAs($other)->get(route('receipts.pdf', $this->payment))->assertForbidden();
    $this->actingAs($other)->get(route('receipts.whatsapp', $this->payment))->assertForbidden();
})->with([RoleName::Guichetier, RoleName::Client, RoleName::Controleur]);
