<?php

use App\Enums\RoleName;
use App\Models\Fare;
use App\Models\Line;
use App\Models\Payment;
use App\Models\Stop;
use App\Models\Subscriber;
use App\Models\Vehicle;
use App\Models\Zone;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-10-06 10:00:00');
    $this->network = networkSetUp();
    $this->manager = userWithRole(RoleName::Gestionnaire);
});

// -- Never used: really deleted -------------------------------------------------------------

it('deletes an unused line with its zones, points and prices', function () {
    $line = $this->network['line'];

    $this->actingAs($this->manager)->delete(route('admin.lines.destroy', $line))
        ->assertRedirect(route('admin.lines.index'))->assertSessionHas('success');

    expect(Line::query()->count())->toBe(0)
        ->and(Zone::query()->count())->toBe(0)
        ->and(Stop::query()->count())->toBe(0)
        ->and(Fare::query()->count())->toBe(0);
});

it('deletes an unused zone and closes the gap in the order', function () {
    ['line' => $line, 'marcory' => $marcory, 'anani' => $anani, 'bassam' => $bassam] = $this->network;

    $this->actingAs($this->manager)->delete(route('admin.lines.zones.destroy', [$line, $anani]))->assertSessionHas('success');

    expect(Zone::query()->find($anani->id))->toBeNull()
        ->and(Stop::query()->where('name', 'Anani')->exists())->toBeFalse()
        ->and($marcory->fresh()->position)->toBe(1)
        ->and($bassam->fresh()->position)->toBe(2);
});

it('deletes an unused boarding point and a vehicle', function () {
    ['line' => $line, 'bassam' => $bassam] = $this->network;
    $stop = $this->network['stops']['Bonoua'];
    $vehicle = Vehicle::factory()->create();

    $this->actingAs($this->manager)->delete(route('admin.lines.zones.stops.destroy', [$line, $bassam, $stop]))->assertSessionHas('success');
    $this->actingAs($this->manager)->delete(route('admin.vehicles.destroy', $vehicle))->assertRedirect(route('admin.vehicles.index'));

    expect(Stop::query()->find($stop->id))->toBeNull()->and(Vehicle::query()->count())->toBe(0);
});

// -- Used: archived, history kept ------------------------------------------------------------

it('archives instead of deleting what receipts or subscribers refer to', function () {
    ['line' => $line, 'bassam' => $bassam] = $this->network;
    $zouglou = $this->network['stops']['Zouglou'];
    Payment::factory()->create(['zone_id' => $bassam->id, 'stop_id' => $zouglou->id]);
    Subscriber::factory()->create(['stop_id' => $this->network['stops']['Bonoua']->id]);

    $this->actingAs($this->manager)->delete(route('admin.lines.zones.stops.destroy', [$line, $bassam, $zouglou]))->assertSessionHas('warning');
    $this->actingAs($this->manager)->delete(route('admin.lines.zones.stops.destroy', [$line, $bassam, $this->network['stops']['Bonoua']]))->assertSessionHas('warning');
    $this->actingAs($this->manager)->delete(route('admin.lines.zones.destroy', [$line, $bassam]))->assertSessionHas('warning');
    $this->actingAs($this->manager)->delete(route('admin.lines.destroy', $line))->assertRedirect(route('admin.lines.show', $line))->assertSessionHas('warning');

    expect($zouglou->fresh()->is_active)->toBeFalse()
        ->and($bassam->fresh()->is_active)->toBeFalse()
        ->and($line->fresh()->is_active)->toBeFalse()
        ->and(Payment::query()->sole()->zone_id)->toBe($bassam->id);
});

// -- Rights and scoping -------------------------------------------------------------------------

it('keeps read-only roles from deleting', function (RoleName $role) {
    ['line' => $line, 'bassam' => $bassam] = $this->network;
    $user = userWithRole($role);

    $this->actingAs($user)->delete(route('admin.lines.destroy', $line))->assertForbidden();
    $this->actingAs($user)->delete(route('admin.lines.zones.destroy', [$line, $bassam]))->assertForbidden();
    $this->actingAs($user)->delete(route('admin.vehicles.destroy', Vehicle::factory()->create()))->assertForbidden();

    expect(Zone::query()->count())->toBe(3);
})->with([RoleName::Direction, RoleName::Comptable, RoleName::Guichetier]);

it('refuses a zone of another line in the URL (404)', function () {
    $other = Line::factory()->create();

    $this->actingAs($this->manager)->delete(route('admin.lines.zones.destroy', [$other, $this->network['bassam']]))->assertNotFound();
    expect(Zone::query()->count())->toBe(3);
});

it('shows the delete button with a confirmation on edit pages', function () {
    ['line' => $line, 'bassam' => $bassam] = $this->network;

    $this->actingAs($this->manager)->get(route('admin.lines.edit', $line))->assertSee('Supprimer la ligne');
    $this->actingAs($this->manager)->get(route('admin.lines.zones.edit', [$line, $bassam]))->assertSee('Supprimer la zone');
    $this->actingAs($this->manager)->get(route('admin.vehicles.edit', Vehicle::factory()->create()))->assertSee('Supprimer le véhicule');
    $this->actingAs(userWithRole(RoleName::SuperAdmin))->get(route('admin.lines.zones.stops.edit', [$line, $bassam, $this->network['stops']['Bonoua']]))->assertSee('Supprimer le point de montée');
});

// -- Search ---------------------------------------------------------------------------------------

it('searches lines by boarding point, vehicles by plate, prices by zone', function () {
    Line::factory()->create(['name' => 'Yopougon – Plateau']);
    Vehicle::factory()->create(['name' => 'Car 1', 'registration' => '1234 AB 01']);
    Vehicle::factory()->create(['name' => 'Car 2', 'registration' => '9876 CD 01']);

    $this->actingAs($this->manager)->get(route('admin.lines.index', ['q' => 'zouglou']))->assertSee('Bonoua – Angré')->assertDontSee('Yopougon');
    $this->actingAs($this->manager)->get(route('admin.vehicles.index', ['q' => '9876']))->assertSee('Car 2')->assertDontSee('Car 1');
    $this->actingAs($this->manager)->get(route('admin.fares.index', ['q' => 'marcory']))->assertSee('Koumassi / Marcory')->assertDontSee('Bonoua / Bassam');
});
