<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Read-only matrix of what each role can do. Permissions are defined in
 * code (PermissionCatalog) and seeded: changing them is a reviewed code
 * change, not a click (DECISIONS.md).
 */
class RoleController extends Controller
{
    public function index(): View
    {
        Gate::authorize('roles.manage');

        $grants = collect(RoleName::cases())
            ->mapWithKeys(fn (RoleName $role) => [$role->value => PermissionCatalog::forRole($role)])
            ->all();

        return view('admin.roles.index', [
            'roles' => RoleName::cases(),
            'groups' => PermissionCatalog::grouped(),
            'grants' => $grants,
        ]);
    }
}
