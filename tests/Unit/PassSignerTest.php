<?php

use App\Contracts\Pass;
use App\Services\Qr\PassSigner;

function fakePass(string $kind = 't', string $uuid = '2107bfc5-bb27-4b36-a643-9b6521fca5b2'): Pass
{
    return new class($kind, $uuid) implements Pass
    {
        public function __construct(private string $kind, private string $uuid) {}

        public function passKind(): string
        {
            return $this->kind;
        }

        public function passUuid(): string
        {
            return $this->uuid;
        }
    };
}

function signer(string $id = 'k1', array $keys = ['k1' => 'first-secret-key-of-at-least-32-bytes!']): PassSigner
{
    return new PassSigner($id, $keys);
}

it('signs a pass with its kind, uuid and key id only', function () {
    $payload = signer()->sign(fakePass());

    expect($payload)->toStartWith('TT1.t.2107bfc5-bb27-4b36-a643-9b6521fca5b2.k1.')
        ->and(explode('.', $payload))->toHaveCount(5)
        ->and(strlen($payload))->toBeLessThan(100);
});

it('verifies its own signature', function (string $kind) {
    $verified = signer()->verify(signer()->sign(fakePass($kind)));

    expect($verified)->not->toBeNull()
        ->and($verified->kind)->toBe($kind)
        ->and($verified->uuid)->toBe('2107bfc5-bb27-4b36-a643-9b6521fca5b2')
        ->and($verified->keyId)->toBe('k1');
})->with(['t', 's']);

it('rejects altered or forged codes', function (string $payload) {
    expect(signer()->verify($payload))->toBeNull();
})->with(function () {
    $good = signer()->sign(fakePass());
    [$v, $k, $u, $id, $sig] = explode('.', $good);

    return [
        'other uuid' => ["{$v}.{$k}.3107bfc5-bb27-4b36-a643-9b6521fca5b2.{$id}.{$sig}"],
        'ticket turned into subscription' => ["{$v}.s.{$u}.{$id}.{$sig}"],
        'truncated signature' => [substr($good, 0, -2)],
        'unknown key id' => ["{$v}.{$k}.{$u}.k9.{$sig}"],
        'other version' => ["TT2.{$k}.{$u}.{$id}.{$sig}"],
        'not a uuid' => ["{$v}.{$k}.12345.{$id}.{$sig}"],
        'garbage' => ['https://example.com'],
        'empty' => [''],
    ];
});

it('rejects a code signed with another secret', function () {
    $forged = signer('k1', ['k1' => 'attacker-secret-key-of-32-bytes-long!!'])->sign(fakePass());

    expect(signer()->verify($forged))->toBeNull();
});

it('keeps accepting codes signed with a previous key after rotation', function () {
    $old = signer('k1', ['k1' => 'first-secret-key-of-at-least-32-bytes!'])->sign(fakePass());
    $rotated = signer('k2', ['k2' => 'second-secret-key-of-at-least-32-bytes', 'k1' => 'first-secret-key-of-at-least-32-bytes!']);

    expect($rotated->verify($old)?->keyId)->toBe('k1')
        ->and($rotated->sign(fakePass()))->toContain('.k2.');

    // Once the old key is removed, old codes stop working.
    expect(signer('k2', ['k2' => 'second-secret-key-of-at-least-32-bytes'])->verify($old))->toBeNull();
});
