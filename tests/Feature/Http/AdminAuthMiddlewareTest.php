<?php

use App\Models\User;

test('super admin without the admin spatie role can still reach admin-gated routes', function () {
    // El super admin se identifica por el flag is_super_admin, no siempre tiene el role
    // Spatie "admin" asignado (depende de si se le eligió al crearlo). Antes de este fix,
    // el middleware AdminAuth solo miraba hasRole('admin') y lo bloqueaba con 401 antes de
    // llegar al mount() de cada componente, aunque ese componente ya supiera tratarlo
    // correctamente como solo-lectura (o, como en procedures.index, ya lo permitía ver).
    $superAdmin = User::factory()->create(['is_super_admin' => true, 'hospital_id' => null]);

    expect($superAdmin->hasRole('admin'))->toBeFalse();

    $this->actingAs($superAdmin)
        ->get(route('procedures.index'))
        ->assertOk();
});

test('a non-admin, non-super-admin user is still blocked from admin-gated routes', function () {
    $user = User::factory()->create(['role' => 'instrumentist']);

    $this->actingAs($user)
        ->get(route('procedures.index'))
        ->assertUnauthorized();
});
