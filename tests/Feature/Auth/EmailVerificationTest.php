<?php

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

function verificationUrl(User $user): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1((string) $user->email),
    ]);
}

it('confirms the e-mail address with the signed link', function () {
    $user = User::factory()->withEmail(verified: false)->role(RoleName::Client)->create();

    $this->actingAs($user)->get(verificationUrl($user))->assertRedirect(route('espace.home'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('refuses a link belonging to someone else', function () {
    $owner = User::factory()->withEmail(verified: false)->role(RoleName::Client)->create();
    $other = User::factory()->withEmail(verified: false)->role(RoleName::Client)->create();

    $this->actingAs($other)->get(verificationUrl($owner))->assertForbidden();

    expect($owner->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('refuses a tampered link', function () {
    $user = User::factory()->withEmail(verified: false)->role(RoleName::Client)->create();

    $this->actingAs($user)->get(verificationUrl($user).'x')->assertForbidden();
});

it('re-sends the confirmation link', function () {
    Notification::fake();
    $user = User::factory()->withEmail(verified: false)->role(RoleName::Client)->create();

    $this->actingAs($user)->post(route('verification.send'))->assertSessionHas('success');

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('has nothing to send without an e-mail address', function () {
    Notification::fake();

    $this->actingAs(userWithRole(RoleName::Client))->post(route('verification.send'))->assertSessionHas('warning');

    Notification::assertNothingSent();
});
