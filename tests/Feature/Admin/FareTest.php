<?php

use App\Actions\Fares\ChangeFareAmount;
use App\Actions\Fares\RemoveFare;
use App\Actions\Fares\ScheduleFare;
use App\Enums\FareType;
use App\Enums\Removal;
use App\Enums\RoleName;
use App\Models\Fare;
use App\Models\Payment;
use App\Models\Zone;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Carbon::setTestNow('2026-10-05 10:00:00');
    $this->zone = Zone::factory()->create(['name' => 'Bonoua / Bassam']);
    $this->current = Fare::factory()->for($this->zone)->create(['amount' => 1500, 'effective_from' => '2026-01-01']);
});

// -- Rule 1: the price applied is the one of the zone in force on the purchase date

it('returns the fare in force on a given date', function () {
    app(ScheduleFare::class)->handle($this->zone, FareType::Daily, 1800, Carbon::parse('2026-11-01'));

    expect($this->zone->fareOn(FareType::Daily, Carbon::parse('2026-10-31'))->amount)->toBe(1500)
        ->and($this->zone->fareOn(FareType::Daily, Carbon::parse('2026-11-01'))->amount)->toBe(1800)
        ->and($this->zone->fareOn(FareType::Daily, Carbon::parse('2027-06-01'))->amount)->toBe(1800)
        ->and($this->zone->fareOn(FareType::Daily, Carbon::parse('2025-12-31')))->toBeNull();
});

it('keeps each formula apart', function () {
    Fare::factory()->for($this->zone)->monthly(50000)->create(['effective_from' => '2026-01-01']);
    Fare::factory()->for($this->zone)->type(FareType::MonthlyEvening, 30000)->create(['effective_from' => '2026-01-01']);

    expect($this->zone->fareOn(FareType::Daily)->amount)->toBe(1500)
        ->and($this->zone->fareOn(FareType::Monthly)->amount)->toBe(50000)
        ->and($this->zone->fareOn(FareType::MonthlyEvening)->amount)->toBe(30000)
        ->and($this->zone->fareOn(FareType::MonthlyMorning))->toBeNull();
});

// -- Rule 6: a new fare applies from its date only

it('closes the current fare the day before the new one starts', function () {
    $new = app(ScheduleFare::class)->handle($this->zone, FareType::Daily, 1800, Carbon::parse('2026-11-01'));

    expect($this->current->fresh()->effective_to->toDateString())->toBe('2026-10-31')
        ->and($new->effective_to)->toBeNull()
        ->and($this->zone->fareOn(FareType::Daily)->amount)->toBe(1500);
});

it('can start today', function () {
    app(ScheduleFare::class)->handle($this->zone, FareType::Daily, 1800, today());

    expect($this->zone->fareOn(FareType::Daily)->amount)->toBe(1800);
});

it('refuses a date in the past', function () {
    app(ScheduleFare::class)->handle($this->zone, FareType::Daily, 1800, Carbon::parse('2026-10-04'));
})->throws(ValidationException::class, 'La date d’effet ne peut pas être dans le passé.');

it('refuses to slip a fare before one already scheduled', function () {
    app(ScheduleFare::class)->handle($this->zone, FareType::Daily, 1800, Carbon::parse('2026-12-01'));

    expect(fn () => app(ScheduleFare::class)->handle($this->zone, FareType::Daily, 1700, Carbon::parse('2026-11-01')))
        ->toThrow(ValidationException::class);
    expect(Fare::query()->where('zone_id', $this->zone->id)->count())->toBe(2);
});

it('cancels a scheduled fare and reopens the current one', function () {
    $scheduled = app(ScheduleFare::class)->handle($this->zone, FareType::Daily, 1800, Carbon::parse('2026-11-01'));

    expect(app(RemoveFare::class)->handle($scheduled))->toBe(Removal::Deleted)
        ->and(Fare::query()->find($scheduled->id))->toBeNull()
        ->and($this->current->fresh()->effective_to)->toBeNull();
});

it('deletes a price never used, and ends a used one without touching history', function () {
    expect(app(RemoveFare::class)->handle($this->current))->toBe(Removal::Deleted)
        ->and($this->zone->fareOn(FareType::Daily))->toBeNull();

    $used = Fare::factory()->for($this->zone)->create(['amount' => 1500, 'effective_from' => '2026-01-01']);
    Payment::factory()->create(['zone_id' => $this->zone->id, 'fare_id' => $used->id]);

    expect(app(RemoveFare::class)->handle($used))->toBe(Removal::Archived)
        ->and($used->fresh()->effective_to->toDateString())->toBe('2026-10-04')
        ->and($this->zone->fareOn(FareType::Daily))->toBeNull();

    // Now an old price used by sales: kept.
    expect(fn () => app(RemoveFare::class)->handle($used->fresh()))->toThrow(ValidationException::class);
});

it('corrects the price in force for the next sales only', function () {
    $payment = Payment::factory()->create(['zone_id' => $this->zone->id, 'fare_id' => $this->current->id, 'amount' => 1500, 'list_amount' => 1500]);

    app(ChangeFareAmount::class)->handle($this->current, 2000);

    expect($this->zone->fareOn(FareType::Daily)->amount)->toBe(2000)
        ->and($payment->fresh()->amount)->toBe(1500)
        ->and(Activity::query()->where('subject_id', $this->current->id)->where('subject_type', Fare::class)->latest('id')->first()->properties['old']['amount'])->toBe(1500);
});

it('lets a manager edit and delete a price from the grid', function () {
    $manager = userWithRole(RoleName::Gestionnaire);

    $this->actingAs($manager)->put(route('admin.fares.update', $this->current), ['amount' => '1 700', 'fare_id' => $this->current->id])
        ->assertRedirect(route('admin.fares.index'))->assertSessionHas('success');
    expect($this->current->fresh()->amount)->toBe(1700);

    $this->actingAs($manager)->put(route('admin.fares.update', $this->current), ['amount' => '0'])->assertSessionHasErrors('amount');

    $this->actingAs($manager)->delete(route('admin.fares.destroy', $this->current))->assertSessionHas('success');
    expect(Fare::query()->find($this->current->id))->toBeNull();
});

it('keeps "direction" and "comptable" from changing prices', function (RoleName $role) {
    $this->actingAs(userWithRole($role))->put(route('admin.fares.update', $this->current), ['amount' => '1'])->assertForbidden();
    $this->actingAs(userWithRole($role))->delete(route('admin.fares.destroy', $this->current))->assertForbidden();
    expect($this->current->fresh()->amount)->toBe(1500);
})->with([RoleName::Direction, RoleName::Comptable]);

it('logs every fare change with old and new values', function () {
    app(ScheduleFare::class)->handle($this->zone, FareType::Daily, 1800, Carbon::parse('2026-11-01'));

    $closed = Activity::query()->where('subject_type', Fare::class)->where('subject_id', $this->current->id)->where('event', 'updated')->sole();
    expect($closed->properties['attributes']['effective_to'])->toStartWith('2026-10-31');
});

// -- Screens

it('lets a manager schedule a price for a zone, typed with spaces', function () {
    $manager = userWithRole(RoleName::Gestionnaire);

    $this->actingAs($manager)->post(route('admin.fares.store'), [
        'zone_id' => $this->zone->id, 'type' => 'monthly_morning', 'amount' => '30 000', 'effective_from' => '2026-11-01',
    ])->assertRedirect(route('admin.fares.index'))->assertSessionHas('success');

    expect(Fare::query()->where('type', 'monthly_morning')->sole())->amount->toBe(30000)->created_by->toBe($manager->id);
});

it('validates the price form in French', function (array $input, string $field) {
    $this->actingAs(userWithRole(RoleName::Gestionnaire))
        ->post(route('admin.fares.store'), [...['zone_id' => $this->zone->id, 'type' => 'daily', 'amount' => '1800', 'effective_from' => '2026-11-01'], ...$input])
        ->assertSessionHasErrors($field);
})->with([
    'past date' => [['effective_from' => '2026-10-01'], 'effective_from'],
    'zero' => [['amount' => '0'], 'amount'],
    'too high' => [['amount' => '5000000'], 'amount'],
    'unknown zone' => [['zone_id' => 999999], 'zone_id'],
    'unknown formula' => [['type' => 'weekly'], 'type'],
]);

it('shows the grid by zone and formula, read only for "direction"', function () {
    app(ScheduleFare::class)->handle($this->zone, FareType::Daily, 1800, Carbon::parse('2026-11-01'));

    $this->actingAs(userWithRole(RoleName::Direction))->get(route('admin.fares.index'))
        ->assertOk()
        ->assertSee('Bonoua / Bassam')
        ->assertSee('Matin simple')
        ->assertSee('Pas de prix : non vendable')
        ->assertSee('Programmé')
        ->assertDontSee('Annuler ce prix programmé');

    $this->actingAs(userWithRole(RoleName::Direction))
        ->post(route('admin.fares.store'), ['zone_id' => $this->zone->id, 'type' => 'daily', 'amount' => '1', 'effective_from' => '2026-11-02'])
        ->assertForbidden();
});
