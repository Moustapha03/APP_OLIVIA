<?php

namespace App\Actions\Users;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\TemporaryPassword;
use Illuminate\Support\Facades\DB;

/**
 * Express customer account created at the ticket office. The agent has
 * informed the customer and recorded their consent (law n° 2013-450).
 * A temporary password lets the customer buy online later.
 */
class CreateCustomer
{
    /**
     * @param  array{first_name: string, last_name: string, phone: string}  $data
     * @return array{0: User, 1: string} the customer and the temporary password
     */
    public function handle(User $agent, array $data): array
    {
        $temporaryPassword = TemporaryPassword::generate();

        $customer = DB::transaction(function () use ($agent, $data, $temporaryPassword) {
            $customer = new User([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'],
                'password' => $temporaryPassword,
            ]);

            $customer->forceFill([
                'status' => UserStatus::Active,
                'must_change_password' => true,
                'privacy_accepted_at' => now(),
            ])->save();

            $customer->syncRoles([RoleName::Client->value]);

            activity('users')->causedBy($agent)->performedOn($customer)
                ->event('customer.created')
                ->log('Client créé au guichet');

            return $customer;
        });

        return [$customer, $temporaryPassword];
    }
}
