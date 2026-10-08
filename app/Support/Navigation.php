<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Navigation menus of each space.
 *
 * An item is shown only when its route exists AND the user holds its
 * permission (null = any authenticated user of that space). Hiding a
 * link is comfort only: routes are always protected server side.
 *
 * @phpstan-type NavItem array{label: string, route: string, icon: string, permission: string|null}
 * @phpstan-type ResolvedItem array{label: string, icon: string, href: string, active: bool}
 */
final class Navigation
{
    public const SPACES = ['espace', 'guichet', 'controle', 'admin'];

    /**
     * Menu definition, grouped by section title ('' = no title).
     *
     * @return array<string, list<NavItem>>
     */
    public static function definition(string $space): array
    {
        return match ($space) {
            'espace' => ['' => [
                ['label' => 'Accueil', 'route' => 'espace.home', 'icon' => 'house', 'permission' => 'space.espace'],
                ['label' => 'Acheter', 'route' => 'espace.purchase.create', 'icon' => 'ticket-plus', 'permission' => 'passes.purchase'],
                ['label' => 'Mes titres', 'route' => 'espace.passes.index', 'icon' => 'qr-code', 'permission' => 'space.espace'],
                ['label' => 'Profil', 'route' => 'espace.profile.edit', 'icon' => 'user-round', 'permission' => 'space.espace'],
            ]],
            'guichet' => ['' => [
                ['label' => 'Abonnement', 'route' => 'guichet.subscriptions.create', 'icon' => 'id-card', 'permission' => 'tickets.sell'],
                ['label' => 'Ticket du jour', 'route' => 'guichet.sales.create', 'icon' => 'ticket', 'permission' => 'tickets.sell'],
                ['label' => 'Abonnés', 'route' => 'guichet.subscribers.index', 'icon' => 'users', 'permission' => 'customers.view'],
                ['label' => 'Ma caisse', 'route' => 'guichet.cash.show', 'icon' => 'wallet', 'permission' => 'cash.open'],
                ['label' => 'Comptes clients', 'route' => 'guichet.customers.index', 'icon' => 'user-round', 'permission' => 'customers.view'],
            ]],
            'controle' => ['' => [
                ['label' => 'Scanner', 'route' => 'controle.scan', 'icon' => 'scan-line', 'permission' => 'scans.create'],
                ['label' => 'Historique', 'route' => 'controle.scans.index', 'icon' => 'history', 'permission' => 'scans.create'],
                ['label' => 'Mon voyage', 'route' => 'controle.trips.current', 'icon' => 'bus', 'permission' => 'trips.manage-own'],
                ['label' => 'Infraction', 'route' => 'controle.infractions.create', 'icon' => 'clipboard-pen', 'permission' => 'infractions.create'],
            ]],
            'admin' => [
                'Pilotage' => [
                    ['label' => 'Tableau de bord', 'route' => 'admin.dashboard', 'icon' => 'layout-dashboard', 'permission' => 'reports.view'],
                    ['label' => 'Rapports', 'route' => 'admin.reports.index', 'icon' => 'chart-column', 'permission' => 'reports.view'],
                ],
                'Clients et paiements' => [
                    ['label' => 'Clients', 'route' => 'admin.subscribers.index', 'icon' => 'users', 'permission' => 'customers.view'],
                    ['label' => 'Paiements et reçus', 'route' => 'admin.receipts.index', 'icon' => 'receipt', 'permission' => 'subscriptions.view-all'],
                    ['label' => 'Encaisser un paiement', 'route' => 'guichet.subscriptions.create', 'icon' => 'banknote', 'permission' => 'tickets.sell'],
                ],
                'Réseau' => [
                    ['label' => 'Lignes, zones et trajets', 'route' => 'admin.lines.index', 'icon' => 'map-pin', 'permission' => 'lines.view'],
                    ['label' => 'Tarifs', 'route' => 'admin.fares.index', 'icon' => 'tag', 'permission' => 'fares.view'],
                    ['label' => 'Véhicules', 'route' => 'admin.vehicles.index', 'icon' => 'bus', 'permission' => 'vehicles.view'],
                ],
                'Finances' => [
                    ['label' => 'Dépenses', 'route' => 'admin.expenses.index', 'icon' => 'wallet-cards', 'permission' => 'expenses.view'],
                    ['label' => 'Caisses', 'route' => 'admin.cash-sessions.index', 'icon' => 'wallet', 'permission' => 'cash.view-all'],
                ],
                'Système' => [
                    ['label' => 'Utilisateurs', 'route' => 'admin.users.index', 'icon' => 'users', 'permission' => 'users.view'],
                    ['label' => 'Rôles', 'route' => 'admin.roles.index', 'icon' => 'shield-check', 'permission' => 'roles.manage'],
                    ['label' => 'Journal d’audit', 'route' => 'admin.audit.index', 'icon' => 'scroll-text', 'permission' => 'audit.view'],
                    ['label' => 'Paramètres', 'route' => 'admin.settings.edit', 'icon' => 'settings', 'permission' => 'settings.manage'],
                    ['label' => 'Charte graphique', 'route' => 'design.index', 'icon' => 'palette', 'permission' => 'design.view'],
                ],
            ],
            default => [],
        };
    }

    /**
     * Items the user may see, with resolved href and active state.
     *
     * $preview shows every item (with "#" links for routes not built yet);
     * it is only used by the /design page.
     *
     * @return array<string, list<ResolvedItem>>
     */
    public static function for(string $space, ?Authenticatable $user, bool $preview = false): array
    {
        $menu = [];

        foreach (self::definition($space) as $section => $items) {
            $visible = [];

            foreach ($items as $item) {
                $exists = Route::has($item['route']);

                if (! $preview && (! $exists || ! self::allows($user, $item['permission']))) {
                    continue;
                }

                $visible[] = [
                    'label' => $item['label'],
                    'icon' => $item['icon'],
                    'href' => $exists ? route($item['route']) : '#',
                    'active' => $exists && request()->routeIs($item['route'].'*'),
                ];
            }

            if ($visible !== []) {
                $menu[$section] = $visible;
            }
        }

        return $menu;
    }

    private static function allows(?Authenticatable $user, ?string $permission): bool
    {
        if ($user === null) {
            return false;
        }

        return $permission === null || Gate::forUser($user)->allows($permission);
    }
}
