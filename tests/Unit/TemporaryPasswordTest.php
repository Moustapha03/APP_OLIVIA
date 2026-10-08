<?php

use App\Support\TemporaryPassword;

it('generates readable temporary passwords without look-alike characters', function () {
    $passwords = collect(range(1, 200))->map(fn () => TemporaryPassword::generate());

    expect($passwords->unique())->toHaveCount(200);

    foreach ($passwords as $password) {
        expect($password)
            ->toMatch('/^[a-z2-9]{4}-[a-z2-9]{4}-[a-z2-9]{4}$/')
            ->not->toMatch('/[01ilo]/');
    }
});
