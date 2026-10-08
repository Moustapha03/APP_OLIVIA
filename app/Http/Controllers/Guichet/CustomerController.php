<?php

namespace App\Http\Controllers\Guichet;

use App\Actions\Users\CreateCustomer;
use App\Actions\Users\ResetUserPassword;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guichet\StoreCustomerRequest;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Customers at the ticket office: find, create, give a temporary password.
 * Only "client" accounts are listed or touched here, never staff.
 */
class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('customers.view');

        $search = $request->string('q')->trim()->toString();
        $phone = PhoneNumber::normalize($search);
        $digits = (string) preg_replace('/\D+/', '', $search);

        $customers = $search === '' ? collect() : User::role(RoleName::Client->value)
            ->where(function (Builder $q) use ($search, $phone, $digits) {
                $q->where('last_name', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%");
                if (strlen($digits) >= 4) {
                    $q->orWhere('phone', 'like', "%{$digits}%");
                }
                if ($phone !== null) {
                    $q->orWhere('phone', $phone);
                }
            })
            ->withCount('subscribers')
            ->orderBy('last_name')
            ->limit(20)
            ->get();

        return view('guichet.customers.index', [
            'search' => $search,
            'customers' => $customers,
            'prefillPhone' => $phone !== null ? PhoneNumber::format($phone) : null,
        ]);
    }

    public function store(StoreCustomerRequest $request, CreateCustomer $create): RedirectResponse
    {
        $agent = $request->user();
        abort_if($agent === null, 403);

        /** @var array{first_name: string, last_name: string, phone: string} $data */
        $data = $request->safe()->only(['first_name', 'last_name', 'phone']);
        [$customer, $temporaryPassword] = $create->handle($agent, $data);

        $target = route('guichet.customers.index', ['q' => $customer->formatted_phone]);

        return redirect($target)
            ->with('success', 'Client '.$customer->name.' créé.')
            ->with('temporary_password', ['for' => $customer->name, 'phone' => $customer->formatted_phone, 'password' => $temporaryPassword]);
    }

    public function resetPassword(Request $request, User $user, ResetUserPassword $reset): RedirectResponse
    {
        Gate::authorize('resetPassword', $user);

        $agent = $request->user();
        abort_if($agent === null, 403);

        $temporaryPassword = $reset->handle($agent, $user);

        return redirect()->route('guichet.customers.index', ['q' => $user->formatted_phone])
            ->with('success', 'Mot de passe provisoire créé pour '.$user->name.'.')
            ->with('temporary_password', ['for' => $user->name, 'phone' => $user->formatted_phone, 'password' => $temporaryPassword]);
    }
}
