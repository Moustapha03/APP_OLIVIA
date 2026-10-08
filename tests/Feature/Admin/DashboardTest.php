<?php

use App\Enums\FareType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\Payment;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Services\Reports\DashboardStats;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-10-20 10:00:00');
    $this->network = networkSetUp();
    $this->stops = $this->network['stops'];

    $number = 0;
    $paid = function (array $attributes) use (&$number) {
        return Payment::factory()->create([
            'status' => PaymentStatus::Succeeded,
            'receipt_number' => sprintf('BA-2026-%04d', ++$number),
            ...$attributes,
        ]);
    };

    // October: 2 subscriptions (one Wave, one discounted), 1 daily ticket, 1 cancelled sale.
    $paid(['purpose' => FareType::Monthly, 'amount' => 50000, 'list_amount' => 50000, 'payment_method' => PaymentMethod::Cash, 'zone_id' => $this->network['bassam']->id, 'stop_id' => $this->stops['Zouglou']->id, 'confirmed_at' => '2026-10-02 08:00']);
    $paid(['purpose' => FareType::MonthlyEvening, 'amount' => 25000, 'list_amount' => 30000, 'payment_method' => PaymentMethod::Wave, 'zone_id' => $this->network['anani']->id, 'stop_id' => $this->stops['Anani']->id, 'confirmed_at' => '2026-10-02 09:00']);
    $paid(['purpose' => FareType::Daily, 'amount' => 1000, 'list_amount' => 1000, 'payment_method' => PaymentMethod::Cash, 'zone_id' => $this->network['anani']->id, 'confirmed_at' => '2026-10-15 07:00']);
    $paid(['purpose' => FareType::Monthly, 'amount' => 40000, 'status' => PaymentStatus::Cancelled, 'zone_id' => $this->network['anani']->id, 'confirmed_at' => '2026-10-03 07:00']);
    // September.
    $paid(['purpose' => FareType::Monthly, 'amount' => 30000, 'list_amount' => 30000, 'payment_method' => PaymentMethod::Cash, 'zone_id' => $this->network['marcory']->id, 'confirmed_at' => '2026-09-28 07:00']);

    // Subscribers: Awa renewed, Koffi did not (to remind), Brou new in October.
    [$awa, $koffi, $brou] = Subscriber::factory()->count(3)->create(['stop_id' => $this->stops['Zouglou']->id]);
    foreach ([$awa, $koffi] as $s) {
        Subscription::factory()->for($s)->create(['starts_on' => '2026-09-01', 'ends_on' => '2026-09-30']);
    }
    Subscription::factory()->for($awa)->create(['starts_on' => '2026-10-01', 'ends_on' => '2026-10-31']);
    Subscription::factory()->for($brou)->create(['starts_on' => '2026-10-01', 'ends_on' => '2026-10-31', 'formula' => FareType::MonthlyMorning]);
});

it('computes the key figures of the month (cancelled sales excluded)', function () {
    $kpis = (new DashboardStats(Carbon::parse('2026-10-01')))->kpis();

    expect($kpis)
        ->revenue->toBe(76000)
        ->previous_revenue->toBe(30000)
        ->receipts->toBe(3)
        ->subscriptions_revenue->toBe(75000)
        ->tickets_revenue->toBe(1000)
        ->tickets->toBe(1)
        ->cash->toBe(51000)
        ->transfers->toBe(25000)
        ->discounts->toBe(5000)
        ->discount_count->toBe(1)
        ->active_subscribers->toBe(2)
        ->previous_active_subscribers->toBe(2)
        ->to_renew->toBe(1);
});

it('builds the chart series: by day, over 12 months, by method, zone and formula', function () {
    $stats = new DashboardStats(Carbon::parse('2026-10-01'));

    $daily = $stats->daily();
    expect($daily['labels'])->toHaveCount(31)
        ->and($daily['subscriptions'][1])->toBe(75000)
        ->and($daily['tickets'][14])->toBe(1000);

    $monthly = $stats->monthly();
    expect($monthly['labels'])->toHaveCount(12)
        ->and(end($monthly['labels']))->toBe('Oct. 26')
        ->and($monthly['subscriptions'][10])->toBe(30000)
        ->and($monthly['subscriptions'][11])->toBe(75000);

    expect($stats->byMethod())->toBe(['Espèces' => 51000, 'Wave' => 25000])
        ->and($stats->byZone())->toBe(['Bonoua / Bassam' => 50000, 'Anani / Port-Bouët' => 26000])
        ->and($stats->byFormula())->toBe(['Matin & soir' => 1, 'Matin simple' => 1, 'Soir simple' => 0]);
});

it('shows the dashboard with figures and charts, for any month', function () {
    $this->actingAs(userWithRole(RoleName::Direction))->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee("76\u{00A0}000\u{00A0}FCFA")
        ->assertSee('+153 % vs mois précédent')
        ->assertSee('Abonnés à relancer')
        ->assertSee('Recettes et dépenses sur 12 mois')
        ->assertSee('x-data="chart"', false)
        ->assertSee('Zouglou');

    $this->actingAs(userWithRole(RoleName::Comptable))->get(route('admin.dashboard', ['mois' => '2026-09']))
        ->assertOk()->assertSee('Septembre 2026')->assertSee("30\u{00A0}000\u{00A0}FCFA");

    $this->actingAs(userWithRole(RoleName::Comptable))->get(route('admin.dashboard', ['mois' => '2026-13']))->assertSessionHasErrors('mois');
});

it('is closed to desk, control and customers', function (RoleName $role) {
    $this->actingAs(userWithRole($role))->get(route('admin.dashboard'))->assertForbidden();
})->with([RoleName::Guichetier, RoleName::Controleur, RoleName::Client]);
