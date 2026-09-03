<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\VoltServiceProvider::class,
    App\Modules\QxLog\Providers\QxLogServiceProvider::class,
    Spatie\Permission\PermissionServiceProvider::class,
];
