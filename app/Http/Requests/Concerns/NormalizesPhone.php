<?php

namespace App\Http\Requests\Concerns;

use App\Support\PhoneNumber;

/**
 * Turns "07 00 00 00 00" into "+2250700000000" before validation.
 * An invalid number is left as typed, so the "phone" rules reject it.
 */
trait NormalizesPhone
{
    protected function normalizePhone(string $field = 'phone'): void
    {
        $raw = $this->input($field);

        if (is_string($raw) && ($normalized = PhoneNumber::normalize($raw)) !== null) {
            $this->merge([$field => $normalized]);
        }
    }

    /**
     * @return list<string>
     */
    protected function phoneRules(): array
    {
        return ['required', 'string', 'regex:'.PhoneNumber::PATTERN];
    }

    /**
     * @return array<string, string>
     */
    protected function phoneMessages(string $field = 'phone'): array
    {
        return [
            "{$field}.required" => 'Indiquez votre numéro de téléphone.',
            "{$field}.regex" => 'Numéro invalide : 10 chiffres commençant par 01, 05, 07, 21, 25 ou 27.',
            "{$field}.unique" => 'Ce numéro a déjà un compte. Connectez-vous, ou passez au guichet si vous avez oublié votre mot de passe.',
        ];
    }
}
