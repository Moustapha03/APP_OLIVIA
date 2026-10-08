<?php

use App\Actions\Users\UpdateUser;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function staffForm(array $overrides = []): array
{
    return [
        'first_name' => 'Grâce',
        'last_name' => 'Kouamé',
        'phone' => '05 11 22 33 44',
        'email' => '',
        'role' => 'guichetier',
        ...$overrides,
    ];
}

it('creates a staff account with a temporary password shown once', function () {
    $manager = superAdmin(); // a manager may not grant the agent role (D143)

    $response = $this->actingAs($manager)->post(route('admin.users.store'), staffForm());

    $user = User::query()->where('phone', '+2250511223344')->sole();
    $temporary = session('temporary_password');

    $response->assertRedirect(route('admin.users.edit', $user));
    expect($temporary)->toMatch('/^[a-z2-9]{4}-[a-z2-9]{4}-[a-z2-9]{4}$/')
        ->and(Hash::check($temporary, $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->getRoleNames()->all())->toBe(['guichetier'])
        ->and(Activity::query()->where('event', 'user.created')->where('causer_id', $manager->id)->exists())->toBeTrue();

    // Shown on the next screen, then never again.
    $this->actingAs($manager)->get(route('admin.users.edit', $user))->assertSee($temporary);
    $this->actingAs($manager)->get(route('admin.users.edit', $user))->assertDontSee($temporary);
});

it('never writes the temporary password to the audit log', function () {
    $this->actingAs(superAdmin())->post(route('admin.users.store'), staffForm());

    expect(Activity::query()->get()->map(fn ($a) => json_encode($a->properties))->implode(''))
        ->not->toContain((string) session('temporary_password'));
});

it('lets the new person log in and forces a new password', function () {
    $this->actingAs(superAdmin())->post(route('admin.users.store'), staffForm());
    $temporary = session('temporary_password');
    auth()->logout();

    $this->post(route('login'), ['login' => '0511223344', 'password' => $temporary]);
    $this->get(route('guichet.cash.show'))->assertRedirect(route('account.password.edit'));
});

it('never lets a manager create a super-admin', function () {
    $this->actingAs(userWithRole(RoleName::Gestionnaire))
        ->post(route('admin.users.store'), staffForm(['role' => 'super-admin']))
        ->assertSessionHasErrors(['role' => 'Vous ne pouvez pas attribuer ce rôle.']);

    expect(User::query()->where('phone', '+2250511223344')->exists())->toBeFalse();
});

it('does not offer the super-admin role to a manager', function () {
    $this->actingAs(userWithRole(RoleName::Gestionnaire))
        ->get(route('admin.users.create'))
        ->assertOk()
        ->assertDontSee('Super-administrateur');
});

it('never lets a manager open or edit a super-admin (IDOR)', function () {
    $root = superAdmin();
    $manager = userWithRole(RoleName::Gestionnaire);

    $this->actingAs($manager)->get(route('admin.users.edit', $root))->assertForbidden();
    $this->actingAs($manager)->put(route('admin.users.update', $root), staffForm(['role' => 'client', 'status' => 'disabled']))->assertForbidden();
    $this->actingAs($manager)->post(route('admin.users.password.reset', $root))->assertForbidden();

    expect($root->fresh()->isSuperAdmin())->toBeTrue()->and($root->fresh()->isActive())->toBeTrue();
});

it('never lets anyone change their own role or disable themselves', function () {
    $manager = userWithRole(RoleName::Gestionnaire);

    $this->actingAs($manager)->put(route('admin.users.update', $manager), staffForm([
        'phone' => $manager->phone, 'role' => 'comptable', 'status' => 'active',
    ]))->assertSessionHasErrors('role');

    $root = superAdmin();
    userWithRole(RoleName::SuperAdmin); // another one exists
    $this->actingAs($root)->put(route('admin.users.update', $root), staffForm([
        'phone' => $root->phone, 'role' => 'super-admin', 'status' => 'disabled',
    ]))->assertSessionHasErrors('role');

    expect($manager->fresh()->getRoleNames()->all())->toBe(['gestionnaire'])
        ->and($root->fresh()->isActive())->toBeTrue();
});

it('lets a super-admin demote another super-admin', function () {
    $root = superAdmin();
    $other = superAdmin();

    $this->actingAs($root)->put(route('admin.users.update', $other), staffForm([
        'phone' => $other->phone, 'role' => 'gestionnaire', 'status' => 'active',
    ]))->assertSessionHasNoErrors();

    expect($other->fresh()->isSuperAdmin())->toBeFalse();
});

it('never demotes or disables the last active super-admin (defence in depth)', function (RoleName $role, UserStatus $status) {
    $root = superAdmin();
    superAdmin()->forceFill(['status' => UserStatus::Disabled])->save(); // a disabled one does not count
    $caller = userWithRole(RoleName::Gestionnaire); // the Action itself does not rely on the policy

    expect(fn () => app(UpdateUser::class)->handle($caller, $root, [
        'first_name' => $root->first_name, 'last_name' => $root->last_name, 'phone' => $root->phone, 'email' => null,
    ], $role, $status))->toThrow(ValidationException::class);

    expect($root->fresh()->isSuperAdmin())->toBeTrue()->and($root->fresh()->isActive())->toBeTrue();
})->with([
    'demoted' => [RoleName::Gestionnaire, UserStatus::Active],
    'disabled' => [RoleName::SuperAdmin, UserStatus::Disabled],
]);

it('changes role and status, logs both, and logs the person out when disabled', function () {
    $manager = superAdmin();
    $agent = userWithRole(RoleName::Guichetier);

    $this->actingAs($manager)->put(route('admin.users.update', $agent), staffForm([
        'phone' => $agent->phone, 'role' => 'controleur', 'status' => 'disabled',
    ]))->assertRedirect(route('admin.users.edit', $agent));

    $agent->refresh();
    expect($agent->getRoleNames()->all())->toBe(['controleur'])
        ->and($agent->isActive())->toBeFalse()
        ->and(Activity::query()->where('event', 'user.role-changed')->sole()->properties->all())->toBe(['old' => 'guichetier', 'new' => 'controleur'])
        ->and(Activity::query()->where('event', 'user.status-changed')->exists())->toBeTrue();

    $this->actingAs($agent)->get(route('controle.scan'))->assertRedirect(route('login'));
});

it('gives a temporary password that unlocks the account', function () {
    $manager = userWithRole(RoleName::Gestionnaire);
    $client = User::factory()->locked()->role(RoleName::Client)->create();

    $this->actingAs($manager)->post(route('admin.users.password.reset', $client))->assertRedirect(route('admin.users.edit', $client));

    $client->refresh();
    expect($client->isLocked())->toBeFalse()
        ->and($client->must_change_password)->toBeTrue()
        ->and(Hash::check((string) session('temporary_password'), $client->password))->toBeTrue();
});

it('searches accounts by name or phone', function () {
    $manager = userWithRole(RoleName::Gestionnaire);
    userWithRole(RoleName::Client, ['first_name' => 'Awa', 'last_name' => 'Koné', 'phone' => '+2250712345678']);
    userWithRole(RoleName::Client, ['first_name' => 'Yao', 'last_name' => 'Kouassi', 'phone' => '+2250199887766']);

    $this->actingAs($manager)->get(route('admin.users.index', ['q' => 'Awa']))->assertSee('Koné')->assertDontSee('Kouassi');
    $this->actingAs($manager)->get(route('admin.users.index', ['q' => '07 12 34 56 78']))->assertSee('Koné')->assertDontSee('Kouassi');
    $this->actingAs($manager)->get(route('admin.users.index', ['q' => '8776']))->assertSee('Kouassi')->assertDontSee('Koné');
    $this->actingAs($manager)->get(route('admin.users.index', ['role' => 'gestionnaire']))->assertDontSee('Koné');
});

it('refuses a phone number already used by another account', function () {
    $manager = userWithRole(RoleName::Gestionnaire);
    userWithRole(RoleName::Client, ['phone' => '+2250511223344']);

    $this->actingAs($manager)->post(route('admin.users.store'), staffForm())
        ->assertSessionHasErrors(['phone' => 'Ce numéro est déjà utilisé par un autre compte.']);
});

it('shows the roles matrix to the super-admin only', function () {
    $this->actingAs(superAdmin())->get(route('admin.roles.index'))->assertOk()->assertSee('Annuler une vente (superviseur)');
    $this->actingAs(userWithRole(RoleName::Gestionnaire))->get(route('admin.roles.index'))->assertForbidden();
});
