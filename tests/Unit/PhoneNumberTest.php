<?php

use App\Support\PhoneNumber;

it('normalizes what people type to E.164', function (string $typed) {
    expect(PhoneNumber::normalize($typed))->toBe('+2250700000006');
})->with([
    '0700000006',
    '07 00 00 00 06',
    '07.00.00.00.06',
    '07-00-00-00-06',
    '+2250700000006',
    '+225 07 00 00 00 06',
    '002250700000006',
    '2250700000006',
]);

it('accepts every Ivorian prefix', function (string $prefix) {
    expect(PhoneNumber::normalize($prefix.'12345678'))->toBe('+225'.$prefix.'12345678');
})->with(['01', '05', '07', '21', '25', '27']);

it('rejects invalid numbers', function (?string $typed) {
    expect(PhoneNumber::normalize($typed))->toBeNull();
})->with([
    null,
    '',
    'abc',
    '12345678',          // old 8-digit format
    '0812345678',        // unknown prefix
    '070000000',         // 9 digits
    '07000000000',       // 11 digits
    '+33612345678',      // foreign
]);

it('formats for display', function () {
    expect(PhoneNumber::format('+2250712345678'))->toBe('07 12 34 56 78');
});
