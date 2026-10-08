<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_category_id' => ExpenseCategory::factory(),
            'spent_on' => today(),
            'amount' => 25000,
            'label' => 'Gasoil',
            'payment_method' => PaymentMethod::Cash,
            'reference' => null,
            'vehicle_id' => null,
            'notes' => null,
        ];
    }
}
