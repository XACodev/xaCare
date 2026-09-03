<?php

use App\Models\User;

test('registration routes are disabled in SaaS mode', function () {
    $this->get('/register')->assertNotFound();

    $this->post('/register', [
        'name' => 'John Doe',
        'username' => 'johndoe',
        'role' => 'doctor',
        'phone' => '12345678',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});
