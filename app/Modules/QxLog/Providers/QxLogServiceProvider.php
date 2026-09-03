<?php

namespace App\Modules\QxLog\Providers;

use Illuminate\Support\ServiceProvider;

class QxLogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            base_path('config/qxlog.php'),
            'qxlog'
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
    }
}
