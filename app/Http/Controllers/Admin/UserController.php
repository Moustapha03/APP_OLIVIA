<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\CreateUser;
use App\Actions\Users\UpdateUser;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'in:'.implode(',', array_map(fn (RoleName $r) => $r->value, RoleName::cases()))],
            'status' => ['nullable', 'string', 'in:active,disabled'],
        ]);

        $users = User::query()
            ->with('roles')
            ->when($filters['q'] ?? null, function (Builder $query, string $search) {
                $phone = PhoneNumber::normalize($search);
                $digits = (string) preg_replace('/\D+/', '', $search);

                $query->where(function (Builder $q) use ($search, $phone, $digits) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");

                    // Part of a number ("56 78"), at least 4 digits to stay meaningful.
                    if (strlen($digits) >= 4) {
                        $q->orWhere('phone', 'like', "%{$digits}%");
                    }
                    if ($phone !== null) {
                        $q->orWhere('phone', $phone);
                    }
                });
            })
            ->when($filters['role'] ?? null, fn (Builder $query, string $role) => $query->role($role))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => RoleName::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.form', [
            'user' => new User,
            'roleOptions' => $this->assignableRoles($request->user(), new User),
            'currentRole' => null,
        ]);
    }

    public function store(UserRequest $request, CreateUser $createUser): RedirectResponse
    {
        [$user, $temporaryPassword] = $createUser->handle($request->user(), $request->identity(), $request->role());

        return redirect()->route('admin.users.edit', $user)
            ->with('success', 'Compte créé.')
            ->with('temporary_password', $temporaryPassword);
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.users.form', [
            'user' => $user,
            'roleOptions' => $this->assignableRoles($request->user(), $user),
            'currentRole' => $user->getRoleNames()->first(),
            'isSelf' => $request->user()?->is($user),
            'canChangeIdentity' => (bool) $request->user()?->can('changeIdentity', $user),
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function update(UserRequest $request, User $user, UpdateUser $updateUser): RedirectResponse
    {
        $updateUser->handle($request->user(), $user, $request->identity(), $request->role(), $request->status());

        return redirect()->route('admin.users.edit', $user)->with('success', 'Compte enregistré.');
    }

    /**
     * Roles the actor may give to this person (UserPolicy::assignRole).
     * The person's current role stays listed, so the form never loses it.
     *
     * @return array<string, string>
     */
    private function assignableRoles(?User $actor, User $target): array
    {
        $current = $target->exists ? $target->getRoleNames()->first() : null;

        return collect(RoleName::cases())
            ->filter(fn (RoleName $role) => $role->value === $current || (bool) $actor?->can('assignRole', [$target, $role]))
            ->mapWithKeys(fn (RoleName $role) => [$role->value => $role->label()])
            ->all();
    }
}
