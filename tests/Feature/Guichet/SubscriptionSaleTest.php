<?php

use App\Enums\FareType;
use App\Enums\PassChannel;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Period;
use App\Enums\RoleName;
use App\Models\Payment;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Services\Photos\SubscriberPhoto;
use App\Services\Qr\PassSigner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Carbon::setTestNow('2026-10-05 09:00:00');
    $setup = counterSetUp();
    $this->agent = $setup['agent'];
    $this->session = $setup['session'];
    $this->stops = $setup['stops'];
    $this->subscriber = Subscriber::factory()->create(['full_name' => 'BROU NESTOR', 'stop_id' => $this->stops['Zouglou']->id]);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function sellSubscription(array $overrides = []): TestResponse
{
    return test()->actingAs(test()->agent)->post(route('guichet.subscriptions.store'), [
        'subscriber_id' => test()->subscriber->id,
        'formula' => 'monthly',
        'month' => '2026-10',
        'payment_method' => 'cash',
        ...$overrides,
    ]);
}

// -- The agency's main job: monthly subscriptions ---------------------------------

it('sells a "Matin & soir" subscription for the month, priced by the zone of the boarding point', function () {
    sellSubscription()->assertRedirect(route('guichet.subscriptions.create'))->assertSessionHas('success');

    $payment = Payment::query()->sole();
    $subscription = Subscription::query()->sole();

    expect($payment)
        ->receipt_number->toBe('BA-2026-0001')
        ->amount->toBe(50000)
        ->list_amount->toBe(50000)
        ->payment_method->toBe(PaymentMethod::Cash)
        ->gateway->toBe('counter')
        ->status->toBe(PaymentStatus::Succeeded)
        ->and($subscription)
        ->subscriber_id->toBe($this->subscriber->id)
        ->formula->toBe(FareType::Monthly)
        ->price_paid->toBe(50000)
        ->channel->toBe(PassChannel::Counter)
        ->and($subscription->starts_on->toDateString())->toBe('2026-10-01')
        ->and($subscription->ends_on->toDateString())->toBe('2026-10-31')
        ->and($subscription->monthLabel())->toBe('octobre 2026');
});

it('sells "Matin simple" and "Soir simple", valid for their period only', function (string $formula, Period $ok, Period $refused) {
    sellSubscription(['formula' => $formula]);

    $subscription = Subscription::query()->sole();
    expect(Payment::query()->sole()->amount)->toBe(30000)
        ->and($subscription->coversPeriod($ok))->toBeTrue()
        ->and($subscription->coversPeriod($refused))->toBeFalse();
})->with([
    ['monthly_morning', Period::Morning, Period::Evening],
    ['monthly_evening', Period::Evening, Period::Morning],
]);

it('sells next month in advance (paid at the end of the month)', function () {
    Carbon::setTestNow('2026-10-28 09:00:00');

    sellSubscription(['month' => '2026-11']);

    expect(Subscription::query()->sole()->starts_on->toDateString())->toBe('2026-11-01');
});

it('refuses a month not on sale, and two subscriptions for the same person and month', function () {
    sellSubscription(['month' => '2026-08'])->assertSessionHasErrors('month');
    sellSubscription(['month' => '2027-01'])->assertSessionHasErrors('month');

    sellSubscription();
    sellSubscription(['formula' => 'monthly_evening'])
        ->assertSessionHasErrors(['month' => 'BROU NESTOR a déjà un abonnement pour octobre 2026.']);

    expect(Subscription::query()->count())->toBe(1);
});

it('refuses a formula without price in the zone', function () {
    $this->subscriber->update(['stop_id' => $this->stops['Marcory']->id]);

    sellSubscription(['formula' => 'monthly_morning'])->assertSessionHasErrors('type');
});

it('requires the subscriber photo, which can be taken at the desk', function () {
    Storage::fake(SubscriberPhoto::DISK);
    $this->subscriber->forceFill(['photo_path' => null])->save();

    sellSubscription()->assertSessionHasErrors('photo');

    sellSubscription(['photo' => UploadedFile::fake()->image('visage.jpg', 600, 800)])->assertSessionHasNoErrors();
    expect($this->subscriber->fresh()->hasPhoto())->toBeTrue();
});

it('never reads the grid price from the form', function () {
    sellSubscription(['list_amount' => 1, 'price' => 1]);

    expect(Payment::query()->sole())->amount->toBe(50000)->list_amount->toBe(50000);
});

// -- Wave and other transfers --------------------------------------------------------

it('records a Wave transfer with its reference, outside the cash drawer', function () {
    sellSubscription(['payment_method' => 'wave', 'external_reference' => 't_4ab7c2xyz']);

    $payment = Payment::query()->sole();
    expect($payment->payment_method)->toBe(PaymentMethod::Wave)
        ->and($payment->external_reference)->toBe('T_4AB7C2XYZ');

    $this->actingAs($this->agent)->get(route('guichet.cash.show'))
        ->assertSee("Mobile Money reçu : 50\u{00A0}000\u{00A0}FCFA")
        ->assertSee("20\u{00A0}000\u{00A0}FCFA"); // drawer = float only
});

it('requires the transfer reference and never records the same transfer twice', function () {
    sellSubscription(['payment_method' => 'wave'])->assertSessionHasErrors(['external_reference' => 'Saisissez la référence du transfert Wave.']);

    sellSubscription(['payment_method' => 'wave', 'external_reference' => 'T_123']);
    $other = Subscriber::factory()->create(['stop_id' => $this->stops['Bonoua']->id]);

    sellSubscription(['subscriber_id' => $other->id, 'payment_method' => 'wave', 'external_reference' => 't_123'])
        ->assertSessionHasErrors(['external_reference' => 'Ce transfert Wave est déjà enregistré (reçu BA-2026-0001).']);
});

// -- Negotiated price (the agency's history shows some) ---------------------------

it('accepts a negotiated price validated on the spot by a manager, and logs who validated it', function () {
    $manager = userWithRole(RoleName::Gestionnaire, ['phone' => '+2250700000009']);

    sellSubscription(['amount' => '25 000', 'discount_reason' => 'Tarif négocié avec la direction', 'approver_login' => '07 00 00 00 09', 'approver_password' => 'password'])
        ->assertSessionHasNoErrors();

    $payment = Payment::query()->sole();
    expect($payment)
        ->amount->toBe(25000)
        ->list_amount->toBe(50000)
        ->discount_reason->toBe('Tarif négocié avec la direction')
        ->discounted_by->toBe($manager->id)
        ->sold_by->toBe($this->agent->id)
        ->and($payment->isDiscounted())->toBeTrue()
        ->and(Activity::query()->where('event', 'sale.discounted')->sole()->properties['discount_approved_by'])->toBe($manager->name);
});

it('never lets the agent who takes the money lower the price alone (audit F1)', function () {
    userWithRole(RoleName::Gestionnaire, ['phone' => '+2250700000009']);
    $colleague = userWithRole(RoleName::Guichetier, ['phone' => '+2250700000008']);

    sellSubscription(['amount' => '1', 'discount_reason' => 'Remise exceptionnelle'])
        ->assertSessionHasErrors(['amount' => 'Prix différent de la grille : un responsable doit le valider avec son téléphone et son mot de passe.']);

    // Wrong password, another desk agent, the agent himself: same refusal, nothing sold.
    foreach ([['07 00 00 00 09', 'faux'], [$colleague->phone, 'password'], [$this->agent->phone, 'password']] as [$login, $password]) {
        sellSubscription(['amount' => '1', 'discount_reason' => 'Remise exceptionnelle', 'approver_login' => $login, 'approver_password' => $password])
            ->assertSessionHasErrors(['approver_login' => 'Validation refusée : identifiants incorrects ou personne non autorisée à accorder un prix.']);
    }

    expect(Payment::query()->count())->toBe(0)
        ->and(Activity::query()->where('event', 'sale.discount.refused')->count())->toBe(3);
});

it('refuses a negotiated price without reason or above the grid, even validated', function () {
    userWithRole(RoleName::Gestionnaire, ['phone' => '+2250700000009']);
    $approval = ['approver_login' => '07 00 00 00 09', 'approver_password' => 'password'];

    sellSubscription(['amount' => '25000', ...$approval])->assertSessionHasErrors('discount_reason');
    sellSubscription(['amount' => '60000', 'discount_reason' => 'Erreur de frappe', ...$approval])->assertSessionHasErrors('amount');

    expect(Payment::query()->count())->toBe(0);
});

it('blocks the manager validation after 5 wrong tries', function () {
    userWithRole(RoleName::Gestionnaire, ['phone' => '+2250700000009']);
    foreach (range(1, 5) as $try) {
        sellSubscription(['amount' => '25000', 'discount_reason' => 'Client fidèle', 'approver_login' => '07 00 00 00 09', 'approver_password' => 'faux']);
    }

    sellSubscription(['amount' => '25000', 'discount_reason' => 'Client fidèle', 'approver_login' => '07 00 00 00 09', 'approver_password' => 'password']);
    expect(session('errors')->first('approver_login'))->toContain('Trop d’essais')
        ->and(Payment::query()->count())->toBe(0);
});

// -- Receipt -----------------------------------------------------------------------------

it('prints a receipt with the client, phone, type, month covered, amount, payment date and a valid QR code', function () {
    $this->subscriber->update(['phone' => '+2250787661418']);
    sellSubscription(['print' => '1', 'payment_method' => 'wave', 'external_reference' => 'T_9']);
    $payment = Payment::query()->sole();

    $this->actingAs($this->agent)->get(route('guichet.sales.receipt', ['payment' => $payment->reference]))
        ->assertOk()
        ->assertSee('Reçu N° BA-2026-0001')
        ->assertSee('BROU NESTOR')
        ->assertSee('Octobre 2026')
        ->assertSee('MATIN &amp; SOIR', false)
        ->assertSeeInOrder([
            'Client', 'BROU NESTOR',
            'Téléphone', '07 87 66 14 18',
            'Type', 'Abonnement mensuel Matin &amp; soir',
            'Mois couvert', 'Octobre 2026',
            'Valable', 'du 01/10/2026 au 31/10/2026',
            'Montant payé', '50 000 FCFA',
            'Date de paiement', '05/10/2026 09:00',
            'Paiement', 'Wave',
            'Réf.', 'T_9',
        ], false);

    expect(app(PassSigner::class)->verify(app(PassSigner::class)->sign(Subscription::query()->sole())))->not->toBeNull();
});

it('numbers receipts one after the other', function () {
    sellSubscription();
    $second = Subscriber::factory()->create(['stop_id' => $this->stops['Anani']->id]);
    sellSubscription(['subscriber_id' => $second->id]);
    $this->actingAs($this->agent)->post(route('guichet.sales.store'), ['zone_id' => $this->stops['Anani']->zone_id, 'payment_method' => 'cash']);

    expect(Payment::query()->orderBy('id')->pluck('receipt_number')->all())->toBe(['BA-2026-0001', 'BA-2026-0002', 'TJ-2026-00001']);
});

// -- Screen ------------------------------------------------------------------------------

it('finds the subscriber, then shows formulas with the prices of their zone and the months', function () {
    $this->actingAs($this->agent)->get(route('guichet.subscriptions.create', ['q' => 'brou']))
        ->assertOk()->assertSee('BROU NESTOR');

    $this->actingAs($this->agent)->get(route('guichet.subscriptions.create', ['abonne' => $this->subscriber->id]))
        ->assertOk()
        ->assertSee('Prix de la zone Bonoua / Bassam')
        ->assertSee('Soir simple')
        ->assertSee("50\u{00A0}000\u{00A0}FCFA")
        ->assertSee('Octobre 2026')
        ->assertSee('Novembre 2026')
        ->assertSee('Wave');
});

it('is closed to other roles', function (RoleName $role) {
    test()->actingAs(userWithRole($role))->post(route('guichet.subscriptions.store'), ['subscriber_id' => test()->subscriber->id])->assertForbidden();
})->with([RoleName::Gestionnaire, RoleName::Controleur, RoleName::Client]);
