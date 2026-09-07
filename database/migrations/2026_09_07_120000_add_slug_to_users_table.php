<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Identificador aleatorio no adivinable para URLs (perfil/edición), en vez del id
     * autoincremental. Backfill idempotente: solo genera slug para filas que no lo tengan.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug', 12)->nullable()->unique()->after('id');
        });

        User::withTrashed()
            ->whereNull('slug')
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    do {
                        $slug = Str::random(12);
                    } while (User::withTrashed()->where('slug', $slug)->exists());

                    $user->newQueryWithoutScopes()->where('id', $user->id)->update(['slug' => $slug]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
