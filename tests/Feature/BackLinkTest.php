<?php

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Modules\QxLog\Models\SurgicalCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('volver desde agendar cirugia apunta a la vista exacta del tablero previo', function () {
    $hospital = Hospital::factory()->create();
    Permission::firstOrCreate(['name' => 'surgeries.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'surgeries.schedule', 'guard_name' => 'web']);
    $user = User::factory()->create(['hospital_id' => $hospital->id]);
    $user->givePermissionTo(['surgeries.view', 'surgeries.schedule']);
    $this->actingAs($user);

    $case = SurgicalCase::factory()->create(['hospital_id' => $hospital->id, 'is_draft' => false]);

    $boardUrl = route('surgeries.board', ['view' => 'kanban', 'room_filter' => 3]);
    $this->get($boardUrl)->assertOk();

    $response = $this->get(route('surgeries.schedule.edit', $case));
    $response->assertOk();

    preg_match_all('/href="(https:\/\/xacare\.test\/surgeries\?[^"]*)"/', $response->getContent(), $matches);
    expect($matches[1])->not->toBeEmpty();
    $backHref = html_entity_decode($matches[1][0]);
    parse_str(parse_url($backHref, PHP_URL_QUERY), $query);
    expect($query)->toMatchArray(['view' => 'kanban', 'room_filter' => '3']);
});

test('volver sin vista previa cae al fallback del modulo', function () {
    $hospital = Hospital::factory()->create();
    Permission::firstOrCreate(['name' => 'surgeries.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'surgeries.schedule', 'guard_name' => 'web']);
    $user = User::factory()->create(['hospital_id' => $hospital->id]);
    $user->givePermissionTo(['surgeries.view', 'surgeries.schedule']);
    $this->actingAs($user);

    $case = SurgicalCase::factory()->create(['hospital_id' => $hospital->id, 'is_draft' => false]);

    $response = $this->get(route('surgeries.schedule.edit', $case));

    $response->assertOk();
    $response->assertSee(e(route('surgeries.board')), false);
});

test('volver desde perfil de paciente no apunta a una url externa', function () {
    $hospital = Hospital::factory()->create();
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $patient = Patient::factory()->for($hospital, 'hospital')->create();

    $response = $this->get(route('patients.show', $patient), ['Referer' => 'https://evil.example.com/steal']);

    $response->assertOk();
    $response->assertDontSee('evil.example.com', false);
    $response->assertSee(e(route('patients.index')), false);
});
