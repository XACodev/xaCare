<?php

use App\Models\Admission;
use App\Models\Hospital;
use App\Models\User;
use App\Support\EncryptedFileStorage;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

function admissionForDocuments(Hospital $hospital, array $overrides = []): Admission
{
    return Admission::query()->create(array_merge([
        'hospital_id' => $hospital->id,
        'patient_id' => \App\Models\Patient::factory()->for($hospital)->create()->id,
        'fecha_ingreso' => now()->toDateString(),
        'completo' => true,
        'va_a_quirofano' => false,
    ], $overrides));
}

function hospitalWithIdDocumentsAddon(bool $enabled): Hospital
{
    $hospital = Hospital::factory()->create();

    if (! $enabled) {
        return $hospital;
    }

    if (\Illuminate\Support\Facades\Schema::hasColumn('hospitals', 'addons')) {
        $hospital->forceFill(['addons' => ['admissions_id_documents']])->save();
    } else {
        $hospital->update([
            'features' => array_values(array_unique([...($hospital->features ?? []), 'admissions_id_documents'])),
        ]);
    }

    return $hospital->fresh();
}

it('serves the decrypted document only to a user of the same hospital', function () {
    route('admissions.documents.show', ['admission' => 1, 'type' => 'dpi']);

    Storage::fake('local');
    $hospital = hospitalWithIdDocumentsAddon(true);
    $admission = admissionForDocuments($hospital);
    EncryptedFileStorage::store('local', "admissions/{$admission->id}/dpi.jpg", 'bytes-del-dpi');
    $admission->update(['dpi_path' => "admissions/{$admission->id}/dpi.jpg"]);

    $user = User::factory()->for($hospital)->create(['role' => 'admin']);

    $this->actingAs($user)
        ->get(route('admissions.documents.show', ['admission' => $admission, 'type' => 'dpi']))
        ->assertOk()
        ->assertSee('bytes-del-dpi', false);
});

it('404s for an admission of another hospital', function () {
    route('admissions.documents.show', ['admission' => 1, 'type' => 'dpi']);

    Storage::fake('local');
    $hospitalA = hospitalWithIdDocumentsAddon(true);
    $hospitalB = hospitalWithIdDocumentsAddon(true);
    $admission = admissionForDocuments($hospitalB, ['dpi_path' => 'admissions/x/dpi.jpg']);

    $user = User::factory()->for($hospitalA)->create(['role' => 'admin']);

    $this->actingAs($user)
        ->get(route('admissions.documents.show', ['admission' => $admission, 'type' => 'dpi']))
        ->assertNotFound();
});

it('403s without the admissions_id_documents addon', function () {
    route('admissions.documents.show', ['admission' => 1, 'type' => 'dpi']);

    Storage::fake('local');
    $hospital = hospitalWithIdDocumentsAddon(false);
    $admission = admissionForDocuments($hospital, ['dpi_path' => 'admissions/x/dpi.jpg']);
    $user = User::factory()->for($hospital)->create(['role' => 'admin']);

    $this->actingAs($user)
        ->get(route('admissions.documents.show', ['admission' => $admission, 'type' => 'dpi']))
        ->assertForbidden();
});
