<?php

namespace App\Http\Controllers\Design;

use App\Http\Controllers\Controller;
use App\Http\Requests\Design\DesignDemoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Living style guide (/design). Read only, fictitious data, super-admin only
 * (route guarded by the 'design.view' ability).
 */
class DesignController extends Controller
{
    public const SPACES = ['public', 'espace', 'guichet', 'controle', 'admin'];

    public const SCAN_STATUSES = ['valid', 'refused', 'warning'];

    public function index(): View
    {
        Gate::authorize('design.view');

        return view('design.index', [
            'stops' => $this->sampleStops(),
            'sales' => $this->sampleSales(),
            'spaces' => self::SPACES,
        ]);
    }

    public function layout(string $space): View
    {
        Gate::authorize('design.view');

        return view("design.previews.{$space}", [
            'stops' => $this->sampleStops(),
            'sales' => $this->sampleSales(),
        ]);
    }

    public function scan(string $status): View
    {
        Gate::authorize('design.view');

        return view('design.scan', ['status' => $status]);
    }

    /**
     * Demonstrates the loading state, server validation and flash messages.
     */
    public function demo(DesignDemoRequest $request): RedirectResponse
    {
        // Short pause so the button spinner is visible.
        usleep(800_000);

        $message = $request->validated('type') === 'confirm'
            ? 'Action confirmée (démonstration : rien n’a été modifié).'
            : 'Formulaire valide (démonstration : rien n’a été enregistré).';

        return redirect()->route('design.index')->with('success', $message)->withFragment($request->validated('type') === 'confirm' ? 'modales' : 'champs');
    }

    /**
     * @return list<array{id: int, name: string, daily: int, monthly: int}>
     */
    private function sampleStops(): array
    {
        return [
            ['id' => 1, 'name' => 'Anani', 'daily' => 1000, 'monthly' => 18000],
            ['id' => 2, 'name' => 'Grand-Bassam', 'daily' => 1500, 'monthly' => 27000],
            ['id' => 3, 'name' => 'Bonoua', 'daily' => 2000, 'monthly' => 36000],
        ];
    }

    /**
     * @return list<array{time: string, customer: string, stop: string, type: string, channel: string, amount: int, status: string}>
     */
    private function sampleSales(): array
    {
        return [
            ['time' => '07:12', 'customer' => 'Awa Koné', 'stop' => 'Grand-Bassam', 'type' => 'Abonnement', 'channel' => 'Mobile Money', 'amount' => 27000, 'status' => 'paid'],
            ['time' => '07:20', 'customer' => 'Yao Kouassi', 'stop' => 'Anani', 'type' => 'Journalier', 'channel' => 'Espèces', 'amount' => 1000, 'status' => 'paid'],
            ['time' => '07:31', 'customer' => 'Fatou Traoré', 'stop' => 'Bonoua', 'type' => 'Journalier', 'channel' => 'Mobile Money', 'amount' => 2000, 'status' => 'pending'],
            ['time' => '07:45', 'customer' => 'Jean-Marc Aka', 'stop' => 'Grand-Bassam', 'type' => 'Journalier', 'channel' => 'Espèces', 'amount' => 1500, 'status' => 'cancelled'],
        ];
    }
}
