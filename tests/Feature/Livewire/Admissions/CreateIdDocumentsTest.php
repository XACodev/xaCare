<?php

use App\Models\Admission;
use App\Models\AdmissionType;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

it('encrypts the uploaded dpi file and links it to the admission', function () {
    Storage::fake('local');
    $hospital = Hospital::factory()->create(['addons' => ['admissions_id_documents']]);
    \App\Support\AdmissionTypeSeeder::seedDefaultsFor($hospital);
    $tipo = AdmissionType::where('hospital_id', $hospital->id)->where('slug', 'coex')->firstOrFail();
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');

    Volt::actingAs($user)->test('admissions.create')
        ->set('admissionTypeId', $tipo->id)
        ->call('selectPatient', $patient->id)
        ->call('nextStep')
        ->call('nextStep')
        ->set('a_fecha_ingreso', now()->toDateString())
        ->set('a_sala_ingreso', 'Medicina Interna')
        ->set('dpiUpload', UploadedFile::fake()->image('dpi.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $admission = Admission::withoutGlobalScopes()->latest('id')->first();

    expect($admission->dpi_path)->not->toBeNull();
    Storage::disk('local')->assertExists($admission->dpi_path);
    expect(Storage::disk('local')->get($admission->dpi_path))->not->toContain('JFIF');
});

it('does not show the dpi/firma fields without the addon', function () {
    $hospital = Hospital::factory()->create(['addons' => []]);
    \App\Support\AdmissionTypeSeeder::seedDefaultsFor($hospital);
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');

    Volt::actingAs($user)->test('admissions.create')
        ->assertDontSeeText('Documento de identidad (DPI)');
});

it('does not persist a dpi/firma file forced by the client when the hospital lacks the addon', function () {
    Storage::fake('local');
    $hospital = Hospital::factory()->create(['addons' => []]);
    \App\Support\AdmissionTypeSeeder::seedDefaultsFor($hospital);
    $tipo = AdmissionType::where('hospital_id', $hospital->id)->where('slug', 'coex')->firstOrFail();
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');

    // El cliente fuerza un archivo aunque el campo no se renderiza (sin addon activo).
    Volt::actingAs($user)->test('admissions.create')
        ->set('admissionTypeId', $tipo->id)
        ->call('selectPatient', $patient->id)
        ->call('nextStep')
        ->call('nextStep')
        ->set('a_fecha_ingreso', now()->toDateString())
        ->set('a_sala_ingreso', 'Medicina Interna')
        ->set('dpiUpload', UploadedFile::fake()->image('dpi.jpg'))
        ->set('firmaUpload', UploadedFile::fake()->image('firma.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $admission = Admission::withoutGlobalScopes()->latest('id')->first();

    expect($admission->dpi_path)->toBeNull();
    expect($admission->firma_path)->toBeNull();
    foreach (Storage::disk('local')->allFiles() as $path) {
        expect($path)->not->toContain('/dpi.')->not->toContain('/firma.');
    }
});
