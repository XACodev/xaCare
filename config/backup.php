<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup Database Path
    |--------------------------------------------------------------------------
    |
    | Directorio donde el comando `backup:database` escribe los volcados de la
    | base de datos. Por defecto usa storage/app/backups (comportamiento de
    | producción sin cambios). Los tests lo sobrescriben con un directorio
    | temporal aislado para no tocar backups reales.
    |
    */

    'database_path' => storage_path('app/backups'),

];
