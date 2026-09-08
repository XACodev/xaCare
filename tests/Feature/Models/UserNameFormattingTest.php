<?php

use App\Models\User;

test('normalizes the user name to title case on save', function () {
    $user = User::factory()->make(['name' => 'JUan ValdEz']);

    expect($user->name)->toBe('Juan Valdez');
});
