<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

/**
 * Usual expense types of a bus company. Editable afterwards in
 * Administration → Dépenses. Idempotent: existing names are kept.
 */
class ExpenseCategorySeeder extends Seeder
{
    public const DEFAULTS = [
        'Carburant',
        'Entretien et réparations',
        'Pièces et pneus',
        'Salaires et primes',
        'Péage et taxes',
        'Assurance',
        'Loyer et charges',
        'Fournitures et impression',
        'Autres',
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $name) {
            ExpenseCategory::query()->firstOrCreate(['name' => $name]);
        }
    }
}
