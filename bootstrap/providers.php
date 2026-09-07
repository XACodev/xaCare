<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\VoltServiceProvider::class,
    App\Modules\QxLog\Providers\QxLogServiceProvider::class,
    App\Modules\Insurance\Providers\InsuranceServiceProvider::class,
    App\Modules\Reports\Providers\ReportsServiceProvider::class,
    Spatie\Permission\PermissionServiceProvider::class,
];
