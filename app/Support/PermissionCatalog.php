<?php

namespace App\Support;

use App\Enums\RoleName;

/**
 * Single source of truth for permissions ("resource.action") and the
 * default permissions of each role. Seeded by RolesAndPermissionsSeeder.
 */
final class PermissionCatalog
{
    /**
     * Every permission, grouped, with a French label (roles screen, step 4).
     *
     * @return array<string, array<string, string>>
     */
    public static function grouped(): array
    {
        return [
            'Espaces' => [
                'space.admin' => 'Accéder à l’administration',
                'space.guichet' => 'Accéder au guichet',
                'space.controle' => 'Accéder au contrôle',
                'space.espace' => 'Accéder à l’espace client',
            ],
            'Réseau' => [
                'lines.view' => 'Voir les lignes',
                'lines.manage' => 'Gérer les lignes',
                'stops.view' => 'Voir les arrêts',
                'stops.manage' => 'Gérer les arrêts',
                'fares.view' => 'Voir les tarifs',
                'fares.manage' => 'Modifier les tarifs',
                'vehicles.view' => 'Voir les véhicules',
                'vehicles.manage' => 'Gérer les véhicules',
            ],
            'Titres de transport' => [
                'passes.purchase' => 'Acheter un titre en ligne (client)',
                'tickets.sell' => 'Vendre à l’agence (tickets et abonnements)',
                'sales.discount' => 'Accorder un prix différent de la grille (avec motif)',
                'tickets.view-all' => 'Voir tous les tickets',
                'tickets.cancel' => 'Annuler une vente (superviseur)',
                'tickets.refund' => 'Rembourser un titre',
                'subscriptions.view-all' => 'Voir tous les abonnements',
                'subscriptions.manage' => 'Gérer les abonnements',
            ],
            'Clients et abonnés' => [
                'customers.view' => 'Rechercher un client ou un abonné',
                'customers.create' => 'Créer ou modifier un client ou un abonné',
                'customers.delete' => 'Supprimer un client ou un abonné',
                'customers.reset-password' => 'Réinitialiser le mot de passe d’un client',
            ],
            'Caisse et paiements' => [
                'cash.open' => 'Ouvrir sa caisse',
                'cash.close' => 'Clôturer sa caisse',
                'cash.view-all' => 'Voir toutes les caisses',
                'cash.reconcile' => 'Rapprocher les caisses',
                'payments.view' => 'Voir les paiements',
                'payments.reconcile' => 'Rapprocher les paiements',
            ],
            'Dépenses' => [
                'expenses.view' => 'Voir les dépenses',
                'expenses.manage' => 'Saisir, modifier et supprimer les dépenses et leurs types',
            ],
            'Contrôle' => [
                'trips.manage-own' => 'Démarrer et terminer ses voyages',
                'scans.create' => 'Scanner les titres',
                'infractions.create' => 'Saisir une infraction',
            ],
            'Pilotage' => [
                'reports.view' => 'Voir le tableau de bord et les rapports',
                'reports.export' => 'Exporter (Excel, PDF)',
            ],
            'Système' => [
                'users.view' => 'Voir les utilisateurs',
                'users.manage' => 'Gérer les utilisateurs',
                'roles.manage' => 'Gérer les rôles et permissions',
                'audit.view' => 'Consulter le journal d’audit',
                'settings.manage' => 'Modifier les paramètres',
                'design.view' => 'Voir la charte graphique',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(array_merge(...array_values(self::grouped())));
    }

    /**
     * Default permissions of a role. super-admin gets everything (and is
     * also granted everything by Gate::before).
     *
     * @return list<string>
     */
    public static function forRole(RoleName $role): array
    {
        return match ($role) {
            RoleName::SuperAdmin => self::all(),

            RoleName::Gestionnaire => [
                'space.admin',
                'lines.view', 'lines.manage', 'stops.view', 'stops.manage',
                'fares.view', 'fares.manage', 'vehicles.view', 'vehicles.manage',
                'tickets.view-all', 'tickets.cancel', 'tickets.refund', 'sales.discount',
                'subscriptions.view-all', 'subscriptions.manage',
                'customers.view', 'customers.create', 'customers.delete', 'customers.reset-password',
                'payments.view',
                'expenses.view', 'expenses.manage',
                'users.view', 'users.manage',
                'reports.view', 'reports.export',
            ],

            // Read only.
            RoleName::Direction => [
                'space.admin',
                'lines.view', 'stops.view', 'fares.view', 'vehicles.view',
                'tickets.view-all', 'subscriptions.view-all', 'payments.view', 'expenses.view',
                'reports.view', 'reports.export',
            ],

            RoleName::Comptable => [
                'space.admin',
                'cash.view-all', 'cash.reconcile',
                'payments.view', 'payments.reconcile',
                'expenses.view', 'expenses.manage',
                'subscriptions.view-all',
                'reports.view', 'reports.export',
            ],

            RoleName::Guichetier => [
                'space.guichet',
                'tickets.sell',
                // No "sales.discount": a price below the grid is validated on the
                // spot by a manager (App\Actions\Sales\ApproveDiscount).
                'customers.view', 'customers.create', 'customers.reset-password',
                'cash.open', 'cash.close',
            ],

            RoleName::Controleur => [
                'space.controle',
                'trips.manage-own', 'scans.create', 'infractions.create',
            ],

            RoleName::Client => [
                'space.espace',
                'passes.purchase',
            ],
        };
    }
}
