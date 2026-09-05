<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Genera un backup de la base de datos activa y limpia backups de más de 7 días';

    public function handle(): int
    {
        $connectionName = config('database.default');
        $connection = config("database.connections.{$connectionName}");

        if (! is_array($connection)) {
            $this->error("Conexión '{$connectionName}' no encontrada en config('database.connections').");

            return self::FAILURE;
        }

        $backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupDir);

        $result = match ($connection['driver'] ?? null) {
            'sqlite' => $this->backupSqlite($connection, $backupDir),
            'mysql', 'mariadb' => $this->backupMysql($connection, $backupDir),
            'pgsql' => $this->backupPgsql($connection, $backupDir),
            default => $this->unsupportedDriver($connection['driver'] ?? 'desconocido'),
        };

        if ($result !== self::SUCCESS) {
            return $result;
        }

        $this->cleanOldBackups($backupDir);

        return self::SUCCESS;
    }

    private function backupSqlite(array $connection, string $backupDir): int
    {
        $sourcePath = $connection['database'] ?? null;

        if (! $sourcePath || ! File::exists($sourcePath)) {
            $this->error("Archivo de base de datos SQLite no encontrado: {$sourcePath}");

            return self::FAILURE;
        }

        $destination = $backupDir.'/'.now()->format('Y-m-d-His').'-database.sqlite';

        File::copy($sourcePath, $destination);

        $this->info("Backup creado en: {$destination}");

        return self::SUCCESS;
    }

    private function backupMysql(array $connection, string $backupDir): int
    {
        if (! $this->binaryAvailable('mysqldump')) {
            $this->error('mysqldump no está disponible en este entorno.');

            return self::FAILURE;
        }

        $destination = $backupDir.'/'.now()->format('Y-m-d-His').'-database.sql';

        $command = [
            'mysqldump',
            '--host='.($connection['host'] ?? '127.0.0.1'),
            '--port='.($connection['port'] ?? '3306'),
            '--user='.($connection['username'] ?? 'root'),
            $connection['database'] ?? '',
        ];

        $result = Process::env([
            'MYSQL_PWD' => $connection['password'] ?? '',
        ])->run($command);

        if (! $result->successful()) {
            $this->error('mysqldump falló: '.$result->errorOutput());

            return self::FAILURE;
        }

        File::put($destination, $result->output());

        $this->info("Backup creado en: {$destination}");

        return self::SUCCESS;
    }

    private function backupPgsql(array $connection, string $backupDir): int
    {
        if (! $this->binaryAvailable('pg_dump')) {
            $this->error('pg_dump no está disponible en este entorno.');

            return self::FAILURE;
        }

        $destination = $backupDir.'/'.now()->format('Y-m-d-His').'-database.sql';

        $command = [
            'pg_dump',
            '--host='.($connection['host'] ?? '127.0.0.1'),
            '--port='.($connection['port'] ?? '5432'),
            '--username='.($connection['username'] ?? 'root'),
            $connection['database'] ?? '',
        ];

        $result = Process::env([
            'PGPASSWORD' => $connection['password'] ?? '',
        ])->run($command);

        if (! $result->successful()) {
            $this->error('pg_dump falló: '.$result->errorOutput());

            return self::FAILURE;
        }

        File::put($destination, $result->output());

        $this->info("Backup creado en: {$destination}");

        return self::SUCCESS;
    }

    private function unsupportedDriver(string $driver): int
    {
        $this->error("Driver de base de datos '{$driver}' no soportado para backups.");

        return self::FAILURE;
    }

    private function binaryAvailable(string $binary): bool
    {
        try {
            return Process::run([$binary, '--version'])->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function cleanOldBackups(string $backupDir): void
    {
        $threshold = now()->subDays(7)->timestamp;

        foreach (File::files($backupDir) as $file) {
            if (File::lastModified($file->getPathname()) < $threshold) {
                File::delete($file->getPathname());
            }
        }
    }
}
