<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    // Directorios aislados bajo el temp del sistema: nunca tocan
    // storage/app/backups ni database/ (rutas reales de la app).
    $this->tempRoot = sys_get_temp_dir().'/xacare-backup-test-'.Str::random(8);
    $this->sqlitePath = $this->tempRoot.'/testing-backup.sqlite';
    $this->backupDir = $this->tempRoot.'/backups';

    File::ensureDirectoryExists($this->tempRoot);

    // Los tests corren con DB_DATABASE=:memory:, que no es un archivo real.
    // El comando necesita una ruta de archivo sqlite real para poder copiarla.
    File::put($this->sqlitePath, '');

    config(['database.connections.sqlite.database' => $this->sqlitePath]);
    config(['backup.database_path' => $this->backupDir]);
});

afterEach(function () {
    // Solo se borra el directorio temporal propio del test, nunca rutas reales.
    File::deleteDirectory($this->tempRoot);
});

test('backup:database crea un archivo de backup sqlite', function () {
    $this->artisan('backup:database')->assertSuccessful();

    $files = collect(File::files($this->backupDir))
        ->map(fn ($file) => $file->getFilename());

    expect($files->filter(fn ($name) => str_ends_with($name, '-database.sqlite')))->not->toBeEmpty();
});

test('backup:database limpia backups con más de 7 días de antigüedad', function () {
    File::ensureDirectoryExists($this->backupDir);

    $oldBackup = $this->backupDir.'/old-database.sqlite';
    File::put($oldBackup, 'contenido viejo');
    touch($oldBackup, now()->subDays(8)->timestamp);

    $this->artisan('backup:database')->assertSuccessful();

    expect(File::exists($oldBackup))->toBeFalse();
});
