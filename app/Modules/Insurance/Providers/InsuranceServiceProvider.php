<?php

namespace App\Modules\Insurance\Providers;

use Illuminate\Support\ServiceProvider;

class InsuranceServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
    }
}
