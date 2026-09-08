<?php

use App\Support\NameFormatter;

test('capitalizes each word and lowercases the rest', function () {
    expect(NameFormatter::titleCase('JUan ValdEz'))->toBe('Juan Valdez');
});

test('lowercases spanish name particles that are not the first word', function () {
    expect(NameFormatter::titleCase('maria DE LA cruz'))->toBe('Maria de la Cruz');
});

test('keeps a leading particle capitalized when it is the first word', function () {
    expect(NameFormatter::titleCase('de la cruz'))->toBe('De la Cruz');
});

test('capitalizes each part of a hyphenated name', function () {
    expect(NameFormatter::titleCase('maria-jose'))->toBe('Maria-Jose');
});

test('capitalizes the letter after an apostrophe', function () {
    expect(NameFormatter::titleCase("o'brien"))->toBe("O'Brien");
});

test('collapses repeated whitespace and trims the ends', function () {
    expect(NameFormatter::titleCase('  juan   valdez  '))->toBe('Juan Valdez');
});

test('returns null for null input', function () {
    expect(NameFormatter::titleCase(null))->toBeNull();
});

test('returns null for an empty or whitespace-only string', function () {
    expect(NameFormatter::titleCase('   '))->toBeNull();
});
