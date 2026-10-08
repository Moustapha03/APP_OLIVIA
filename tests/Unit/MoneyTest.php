<?php

use App\Support\Money;

it('formats FCFA amounts with non-breaking spaces and no decimals', function (int $amount, string $expected) {
    expect(Money::format($amount))->toBe(str_replace(' ', "\u{00A0}", $expected));
})->with([
    [0, '0 FCFA'],
    [500, '500 FCFA'],
    [1500, '1 500 FCFA'],
    [27000, '27 000 FCFA'],
    [1250000, '1 250 000 FCFA'],
]);

it('can omit the currency', function () {
    expect(Money::format(36000, withCurrency: false))->toBe("36\u{00A0}000");
});
