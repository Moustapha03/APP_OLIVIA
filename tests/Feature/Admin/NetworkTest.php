<?php

use App\Enums\RoleName;
use App\Models\Line;
use App\Models\Stop;
use App\Models\Vehicle;
use App\Models\Zone;
use Spatie\Activitylog\Models\Activity;

// -- Lines -----------------------------------------------------------------------

it('creates and updates a line towards its destination', function () {
    $manager = userWithRole(RoleName::Gestionnaire);

    $this->actingAs($manager)->post(route('admin.lines.store'), [
        'code' => 'angre', 'name' => 'Bonoua – Angré', 'destination_name' => 'Angré', 'is_active' => '1',
    ])->assertRedirect();

    $line = Line::query()->where('code', 'ANGRE')->sole();
    expect($line->destination_name)->toBe('Angré');
});

// -- Zones and boarding points ------------------------------------------------------

it('adds zones farther and farther from the destination, then reorders them', function () {
    $line = Line::factory()->create();
    $manager = userWithRole(RoleName::Gestionnaire);

    foreach (['Koumassi / Marcory', 'Anani / Port-Bouët', 'Bonoua / Bassam'] as $name) {
        $this->actingAs($manager)->post(route('admin.lines.zones.store', $line), ['name' => $name, 'is_active' => '1'])->assertRedirect();
    }
    expect($line->zones()->pluck('name', 'position')->all())->toBe([1 => 'Koumassi / Marcory', 2 => 'Anani / Port-Bouët', 3 => 'Bonoua / Bassam']);

    $bassam = Zone::query()->where('name', 'Bonoua / Bassam')->sole();
    $this->actingAs($manager)->post(route('admin.lines.zones.move', [$line, $bassam]), ['direction' => 'up']);
    expect($line->zones()->pluck('name')->all())->toBe(['Koumassi / Marcory', 'Bonoua / Bassam', 'Anani / Port-Bouët']);
});

it('adds and renames boarding points of a zone', function () {
    $zone = Zone::factory()->create();
    $manager = userWithRole(RoleName::Gestionnaire);

    $this->actingAs($manager)->post(route('admin.lines.zones.stops.store', [$zone->line, $zone]), ['name' => 'Zouglou', 'is_active' => '1'])->assertRedirect();
    $stop = Stop::query()->where('name', 'Zouglou')->sole();

    $this->actingAs($manager)->put(route('admin.lines.zones.stops.update', [$zone->line, $zone, $stop]), ['name' => 'Carrefour Zouglou', 'is_active' => '1'])->assertRedirect();
    expect($stop->fresh()->name)->toBe('Carrefour Zouglou');

    $this->actingAs($manager)->post(route('admin.lines.zones.stops.store', [$zone->line, $zone]), ['name' => 'Carrefour Zouglou'])
        ->assertSessionHasErrors(['name' => 'Ce point de montée existe déjà dans la zone.']);
});

it('refuses a zone or a point reached through another line or zone (scoped bindings)', function () {
    $stop = Stop::factory()->create();
    $otherZone = Zone::factory()->create();
    $manager = userWithRole(RoleName::Gestionnaire);

    $this->actingAs($manager)->get(route('admin.lines.zones.edit', [$otherZone->line, $stop->zone]))->assertNotFound();
    $this->actingAs($manager)->get(route('admin.lines.zones.stops.edit', [$otherZone->line, $otherZone, $stop]))->assertNotFound();
});

it('applies a pass of a zone to that zone and every zone closer to the destination (rule 3)', function () {
    ['marcory' => $marcory, 'anani' => $anani, 'bassam' => $bassam] = networkSetUp();
    $elsewhere = Zone::factory()->create();

    expect($marcory->isCoveredBy($anani))->toBeTrue()
        ->and($anani->isCoveredBy($anani))->toBeTrue()
        ->and($bassam->isCoveredBy($anani))->toBeFalse()
        ->and($elsewhere->isCoveredBy($bassam))->toBeFalse();
});

it('shows the line with its zones, points and prices', function () {
    ['line' => $line] = networkSetUp();

    $this->actingAs(userWithRole(RoleName::Direction))->get(route('admin.lines.show', $line))
        ->assertOk()
        ->assertSee('Bonoua / Bassam')
        ->assertSee('Zouglou')
        ->assertSee("50\u{00A0}000\u{00A0}FCFA")
        ->assertSee('Pas de tarif')        // Marcory has no "simple" price
        ->assertDontSee('Ajouter la zone'); // read only
});

it('logs zone changes', function () {
    $zone = Zone::factory()->create(['name' => 'Bassam']);

    $this->actingAs(userWithRole(RoleName::Gestionnaire))
        ->put(route('admin.lines.zones.update', [$zone->line, $zone]), ['name' => 'Bonoua / Bassam', 'is_active' => '1']);

    $entry = Activity::query()->where('subject_type', Zone::class)->where('event', 'updated')->sole();
    expect($entry->properties['old']['name'])->toBe('Bassam')->and($entry->properties['attributes']['name'])->toBe('Bonoua / Bassam');
});

it('keeps the network closed to the accountant and read only for "direction"', function () {
    $line = Line::factory()->create();

    $this->actingAs(userWithRole(RoleName::Direction))->post(route('admin.lines.zones.store', $line), ['name' => 'X'])->assertForbidden();
    $this->actingAs(userWithRole(RoleName::Comptable))->get(route('admin.lines.index'))->assertForbidden();
});

// -- Vehicles --------------------------------------------------------------------

it('creates and updates a vehicle', function () {
    $manager = userWithRole(RoleName::Gestionnaire);
    $line = Line::factory()->create();

    $this->actingAs($manager)->post(route('admin.vehicles.store'), [
        'name' => 'Bus 12', 'registration' => ' 1234  ab 01 ', 'capacity' => 70, 'line_id' => $line->id, 'is_active' => '1',
    ])->assertRedirect(route('admin.vehicles.index'));

    $vehicle = Vehicle::query()->sole();
    expect($vehicle->registration)->toBe('1234 AB 01');
});

// -- Public home -----------------------------------------------------------------

it('shows the price grid by zone on the home page', function () {
    networkSetUp();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Nos tarifs vers Angré')
        ->assertSee('Bonoua / Bassam')
        ->assertSee('Zouglou')
        ->assertSee('Matin simple')
        ->assertSee("1\u{00A0}500\u{00A0}FCFA")
        ->assertSee("40\u{00A0}000\u{00A0}FCFA");
});
