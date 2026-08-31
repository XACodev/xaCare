<?php

use App\Support\MoneyToWords;

test('spells whole amounts in quetzales', function () {
    expect(MoneyToWords::spell(2655.50))
        ->toBe('Dos mil seiscientos cincuenta y cinco quetzales con 50/100');
});

test('spells amounts with zero cents', function () {
    expect(MoneyToWords::spell(100.00))
        ->toBe('Cien quetzales con 00/100');
});

test('uses singular "quetzal" for amount of exactly one', function () {
    expect(MoneyToWords::spell(1.00))
        ->toBe('Un quetzal con 00/100');
});

test('uses singular "quetzal" with cents', function () {
    expect(MoneyToWords::spell(1.25))
        ->toBe('Un quetzal con 25/100');
});

test('spells zero amount', function () {
    expect(MoneyToWords::spell(0.00))
        ->toBe('Cero quetzales con 00/100');
});

test('pads single-digit cents with a leading zero', function () {
    expect(MoneyToWords::spell(10.05))
        ->toBe('Diez quetzales con 05/100');
});
