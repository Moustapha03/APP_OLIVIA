<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Types of expense: add, rename, remove (archived once used, so totals
 * of past months keep their label).
 */
class ExpenseCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('expenses.manage');

        $data = $request->validateWithBag('category', [
            'name' => ['required', 'string', 'min:2', 'max:80', Rule::unique('expense_categories', 'name')],
        ], ['name.required' => 'Indiquez le nom du type de dépense.', 'name.unique' => 'Ce type de dépense existe déjà.']);

        ExpenseCategory::query()->create(['name' => trim($data['name']), 'is_active' => true]);

        return back()->with('success', 'Type de dépense « '.trim($data['name']).' » ajouté.');
    }

    public function update(Request $request, ExpenseCategory $category): RedirectResponse
    {
        Gate::authorize('expenses.manage');

        $data = $request->validateWithBag('category_'.$category->id, [
            'name' => ['required', 'string', 'min:2', 'max:80', Rule::unique('expense_categories', 'name')->ignore($category->id)],
            'is_active' => ['nullable', 'boolean'],
        ], ['name.required' => 'Indiquez le nom du type de dépense.', 'name.unique' => 'Ce type de dépense existe déjà.']);

        $category->update(['name' => trim($data['name']), 'is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Type de dépense enregistré.');
    }

    public function destroy(ExpenseCategory $category): RedirectResponse
    {
        Gate::authorize('expenses.manage');

        if (Expense::withTrashed()->where('expense_category_id', $category->id)->exists()) {
            $category->update(['is_active' => false]);

            return back()->with('warning', '« '.$category->name.' » a déjà des dépenses : il est archivé (plus proposé) et reste dans les totaux passés.');
        }

        $category->delete();

        return back()->with('success', 'Type de dépense « '.$category->name.' » supprimé.');
    }
}
