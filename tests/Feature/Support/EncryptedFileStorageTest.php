<?php

use App\Support\EncryptedFileStorage;
use Illuminate\Support\Facades\Storage;

it('never writes plaintext to disk', function () {
    Storage::fake('local');

    EncryptedFileStorage::store('local', 'documents/test.txt', 'contenido sensible');

    $raw = Storage::disk('local')->get('documents/test.txt');

    expect($raw)->not->toContain('contenido sensible');
});

it('round-trips content through encryption', function () {
    Storage::fake('local');

    EncryptedFileStorage::store('local', 'documents/test.txt', 'contenido sensible');

    expect(EncryptedFileStorage::retrieve('local', 'documents/test.txt'))->toBe('contenido sensible');
});
