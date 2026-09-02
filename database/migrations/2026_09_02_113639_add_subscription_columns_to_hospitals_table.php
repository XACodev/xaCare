<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hospitals', function (Blueprint $table) {
            $table->string('subscription_status')->default('active')->after('is_active');
            $table->timestamp('trial_ends_at')->nullable()->after('subscription_status');
            $table->string('stripe_id')->nullable()->index()->after('trial_ends_at');
            $table->string('pm_type')->nullable()->after('stripe_id');
            $table->string('pm_last_four', 4)->nullable()->after('pm_type');
        });
    }

    public function down(): void
    {
        Schema::table('hospitals', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_status',
                'trial_ends_at',
                'stripe_id',
                'pm_type',
                'pm_last_four',
            ]);
        });
    }
};
