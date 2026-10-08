<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/**
 * @param  array<string, list<string>>  $errors
 */
function renderWithErrors(string $template, array $errors = []): string
{
    $bag = new ViewErrorBag;
    $bag->put('default', new MessageBag($errors));

    // Shared like the ShareErrorsFromSession middleware does on web requests.
    View::share('errors', $bag);

    return Blade::render($template);
}

it('renders a link when the button has an href', function () {
    $html = renderWithErrors('<x-button href="/espace">Mes titres</x-button>');

    expect($html)->toContain('<a href="/espace"')->not->toContain('<button');
});

it('defaults buttons to type button and marks submit buttons for the loading state', function () {
    expect(renderWithErrors('<x-button>Ok</x-button>'))
        ->toContain('type="button"')
        ->not->toContain('data-loading');

    expect(renderWithErrors('<x-button type="submit" loading-text="Paiement en cours…">Payer</x-button>'))
        ->toContain('type="submit"')
        ->toContain('data-loading')
        ->toContain('data-loading-text="Paiement en cours…"');
});

it('links an input to its label, hint and error', function () {
    $html = renderWithErrors(
        '<x-input name="phone" label="Téléphone" hint="10 chiffres" required />',
        ['phone' => ['Numéro invalide.']],
    );

    expect($html)
        ->toContain('for="phone"')
        ->toContain('id="phone"')
        ->toContain('aria-describedby="phone-hint phone-error"')
        ->toContain('aria-invalid="true"')
        ->toContain('Numéro invalide.')
        ->toContain('required');
});

it('marks optional fields as such', function () {
    expect(renderWithErrors('<x-input name="name" label="Nom" />'))->toContain('(facultatif)');
    expect(renderWithErrors('<x-input name="name" label="Nom" required />'))->not->toContain('(facultatif)');
});

it('never pre-fills a password field', function () {
    expect(renderWithErrors('<x-input name="password" type="password" value="secret" />'))
        ->not->toContain('value="secret"')
        ->toContain('data-password-toggle="password"');
});

it('escapes user content in components', function () {
    $html = Blade::render('<x-badge>{{ $name }}</x-badge>', ['name' => '<script>alert(1)</script>']);

    expect($html)->not->toContain('<script>')->toContain('&lt;script&gt;');
});

it('formats amounts in FCFA', function () {
    expect(Blade::render('<x-money :amount="27000" />'))->toContain("27\u{00A0}000\u{00A0}FCFA");
});

it('renders a QR code as inline SVG without XML prolog', function () {
    $html = Blade::render('<x-qr payload="DEMO" label="QR de test" />');

    expect($html)
        ->toContain('<svg')
        ->toContain('aria-label="QR de test"')
        ->not->toContain('<?xml');
});

it('shows the table empty state when there are no rows', function () {
    expect(Blade::render('<x-table empty="Aucune vente."><x-slot:head><th>A</th></x-slot:head></x-table>'))
        ->toContain('Aucune vente.');
});

it('submits confirmations as real forms with CSRF and method spoofing', function () {
    $html = renderWithErrors('<x-confirm name="c" title="Vraiment ?" action="/x" method="DELETE">Définitif.</x-confirm>');

    expect($html)
        ->toContain('action="/x"')
        ->toContain('name="_token"')
        ->toContain('name="_method" value="DELETE"');
});
