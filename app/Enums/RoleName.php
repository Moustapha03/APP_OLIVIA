<?php

namespace App\Enums;

/**
 * Application roles. Business code never checks a role: it checks
 * permissions. Roles are only used to seed permissions, to protect the
 * super-admin account and in Gate::before (see DECISIONS.md D22).
 */
enum RoleName: string
{
    case SuperAdmin = 'super-admin';
    case Gestionnaire = 'gestionnaire';
    case Direction = 'direction';
    case Comptable = 'comptable';
    case Guichetier = 'guichetier';
    case Controleur = 'controleur';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super-administrateur',
            self::Gestionnaire => 'Gestionnaire',
            self::Direction => 'Direction',
            self::Comptable => 'Comptable',
            self::Guichetier => 'Guichetier',
            self::Controleur => 'Contrôleur',
            self::Client => 'Client',
        };
    }
}
