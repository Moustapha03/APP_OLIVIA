<?php

use App\Enums\RoleName;
use App\Models\Subscriber;
use App\Models\Subscription;
use Illuminate\Support\Carbon;

it('ships a valid manifest whose icons exist', function () {
    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)
        ->name->toContain('Transport')
        ->display->toBe('standalone')
        ->start_url->toStartWith('/dashboard')
        ->lang->toBe('fr');

    $purposes = collect($manifest['icons'])->pluck('purpose')->all();
    expect($purposes)->toContain('any')->toContain('maskable');

    foreach ([...$manifest['icons'], ...collect($manifest['shortcuts'])->flatMap(fn ($s) => $s['icons'])->all()] as $icon) {
        expect(file_exists(public_path(ltrim($icon['src'], '/'))))->toBeTrue("Missing {$icon['src']}");
    }
});

it('links the manifest and icons on every page', function () {
    $this->get(route('login'))
        ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
        ->assertSee('<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">', false);
});

it('serves the offline page to anyone, without personal data', function () {
    $this->get(route('offline'))->assertOk()->assertSee('Pas de connexion internet')->assertSee('Mes titres enregistrés');
});

it('has a service worker that never caches forms, the API or downloads', function () {
    $sw = (string) file_get_contents(public_path('sw.js'));

    expect($sw)
        ->toContain("const OFFLINE_URL = '/hors-ligne';")
        ->toContain("request.method !== 'GET'")
        ->toContain("url.pathname.startsWith('/api/')")
        ->toContain('/\/pdf$/')
        ->toContain("data.type === 'clear'");
});

it('asks the browser to erase offline data at logout', function () {
    $this->actingAs(userWithRole(RoleName::Client))->post(route('logout'))
        ->assertRedirect(route('home'))
        ->assertHeader('Clear-Site-Data', '"storage"');
});

it('marks the client valid passes to keep them offline', function () {
    Carbon::setTestNow('2026-10-06 08:00:00');
    $client = userWithRole(RoleName::Client);
    $subscriber = Subscriber::factory()->create(['user_id' => $client->id]);
    $running = Subscription::factory()->for($subscriber)->create(['starts_on' => '2026-10-01', 'ends_on' => '2026-10-31']);
    $old = Subscription::factory()->for($subscriber)->create(['starts_on' => '2026-08-01', 'ends_on' => '2026-08-31']);

    $html = $this->actingAs($client)->get(route('espace.passes.index'))->assertOk()->getContent();

    expect($html)->toMatch('#<a href="'.preg_quote(route('espace.subscriptions.show', $running->uuid), '#').'"\s+data-offline-page#')
        ->and($html)->not->toMatch('#<a href="'.preg_quote(route('espace.subscriptions.show', $old->uuid), '#').'"\s+data-offline-page#');
});
