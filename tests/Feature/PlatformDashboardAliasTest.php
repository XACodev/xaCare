<?php

use App\Models\User;

test('/platform/dashboard responde con éxito (vía redirect) para un admin de plataforma', function () {
    $admin = User::factory()->withTwoFactor()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    $this->actingAs($admin)
        ->get('/platform/dashboard')
        ->assertRedirect('/platform');

    $this->actingAs($admin)
        ->followingRedirects()
        ->get('/platform/dashboard')
        ->assertOk();
});
