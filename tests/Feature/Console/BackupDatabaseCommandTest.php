<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Los tests corren con DB_DATABASE=:memory:, que no es un archivo real.
    // El comando necesita una ruta de archivo sqlite real para poder copiarla.
    $sqlitePath = database_path('testing-backup.sqlite');
    File::put($sqlitePath, '');
    config(['database.connections.sqlite.database' => $sqlitePath]);
});

afterEach(function () {
    File::delete(database_path('testing-backup.sqlite'));
});

test('backup:database crea un archivo de backup sqlite', function () {
    $backupDir = storage_path('app/backups');
    File::deleteDirectory($backupDir);

    $this->artisan('backup:database')->assertSuccessful();

    $files = collect(File::files($backupDir))
        ->map(fn ($file) => $file->getFilename());

    expect($files->filter(fn ($name) => str_ends_with($name, '-database.sqlite')))->not->toBeEmpty();
});

test('backup:database limpia backups con más de 7 días de antigüedad', function () {
    $backupDir = storage_path('app/backups');
    File::deleteDirectory($backupDir);
    File::ensureDirectoryExists($backupDir);

    $oldBackup = $backupDir.'/old-database.sqlite';
    File::put($oldBackup, 'contenido viejo');
    touch($oldBackup, now()->subDays(8)->timestamp);

    $this->artisan('backup:database')->assertSuccessful();

    expect(File::exists($oldBackup))->toBeFalse();
});
