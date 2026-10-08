<?php

namespace App\Services\Qr;

use App\Contracts\Pass;
use RuntimeException;

/**
 * Content of a pass QR code:
 *
 *     TT1.<kind>.<uuid>.<key id>.<signature>
 *     TT1.s.9b2f…-…-….k1.Xy3…   (≈ 95 characters)
 *
 * kind: "t" ticket, "s" subscription. The signature is
 * HMAC-SHA256("TT1.<kind>.<uuid>.<key id>", key), base64url. No personal
 * data. Key rotation: set a new QR_SIGNING_KEY_ID / QR_SIGNING_KEY and keep
 * the old pair in QR_SIGNING_PREVIOUS_KEYS ("id:key,id:key") until every
 * pass signed with it has expired.
 */
final class PassSigner
{
    public const VERSION = 'TT1';

    private const KINDS = ['t', 's'];

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    /**
     * @param  array<string, string>  $keys  key id => secret (current first)
     */
    public function __construct(
        private readonly string $currentKeyId,
        private readonly array $keys,
    ) {}

    public static function fromConfig(): self
    {
        $currentId = (string) config('transport.qr.key_id', 'k1');
        $current = (string) config('transport.qr.key');

        if ($current === '') {
            throw new RuntimeException('QR_SIGNING_KEY is not set (see .env.example).');
        }

        $keys = [$currentId => self::decodeKey($current)];

        foreach (array_filter(array_map('trim', explode(',', (string) config('transport.qr.previous_keys')))) as $pair) {
            [$id, $secret] = array_pad(explode(':', $pair, 2), 2, '');
            if ($id !== '' && $secret !== '' && ! isset($keys[$id])) {
                $keys[$id] = self::decodeKey($secret);
            }
        }

        return new self($currentId, $keys);
    }

    public function sign(Pass $pass): string
    {
        $body = implode('.', [self::VERSION, $pass->passKind(), $pass->passUuid(), $this->currentKeyId]);

        return $body.'.'.$this->signature($body, $this->keys[$this->currentKeyId]);
    }

    /**
     * Checks a scanned QR content. Returns null when it is not one of our
     * codes or its signature is wrong (forged, altered, unknown key).
     */
    public function verify(string $payload): ?SignedPass
    {
        $parts = explode('.', trim($payload));

        if (count($parts) !== 5) {
            return null;
        }

        [$version, $kind, $uuid, $keyId, $signature] = $parts;

        if ($version !== self::VERSION || ! in_array($kind, self::KINDS, true) || preg_match(self::UUID, $uuid) !== 1) {
            return null;
        }

        $key = $this->keys[$keyId] ?? null;

        if ($key === null) {
            return null;
        }

        $expected = $this->signature(implode('.', [$version, $kind, $uuid, $keyId]), $key);

        return hash_equals($expected, $signature) ? new SignedPass($kind, $uuid, $keyId) : null;
    }

    private function signature(string $body, string $key): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $body, $key, true)), '+/', '-_'), '=');
    }

    private static function decodeKey(string $key): string
    {
        $decoded = base64_decode($key, true);

        return $decoded !== false && strlen($decoded) >= 32 ? $decoded : $key;
    }
}
