<?php

use App\Models\Hospital;
use App\Models\User;

test('el sidebar usa las clases de marca teal, no indigo', function () {
    $blade = file_get_contents(resource_path('views/components/layouts/app/sidebar.blade.php'));

    expect($blade)->not->toContain('bg-indigo-100');
    expect($blade)->not->toContain('bg-indigo-400');
    expect($blade)->not->toContain('border-indigo-600');
    expect($blade)->toContain('bg-mist');
    expect($blade)->toContain('text-accent');
});

test('las etiquetas del sidebar en ingles tienen traduccion en es.json', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);

    $this->actingAs($admin)->get('/dashboard')
        ->assertOk()
        ->assertDontSee('Payouts')
        ->assertDontSee('History')
        ->assertDontSee('General Settings')
        ->assertDontSee('Make Payment')
        ->assertDontSee('Payment History')
        ->assertDontSee('Procedure History');
});
