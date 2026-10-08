<?php

use App\Enums\RoleName;

it('shows a plain French 403 page without inline styles', function () {
    $response = $this->actingAs(userWithRole(RoleName::Client))->get(route('admin.dashboard'));

    $response->assertForbidden()->assertSee('Accès non autorisé')->assertSee('Retour à l’accueil', false);
    expect($response->getContent())->not->toContain('<style')->not->toContain('style="');
});

it('shows a plain French 404 page', function () {
    $this->get('/cette-page-n-existe-pas')->assertNotFound()->assertSee('Page introuvable');
});
