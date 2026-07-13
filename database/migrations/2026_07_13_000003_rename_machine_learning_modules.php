<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('modulos')->where('url', 'modelosml')->update([
            'name' => 'Modelos de aprendizaje automatico',
            'updated_at' => now(),
        ]);

        DB::table('modulos')->where('url', 'modelosmlsuite')->update([
            'name' => 'Entrenamiento de modelos de aprendizaje automatico',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('modulos')->where('url', 'modelosml')->update([
            'name' => 'Modelos ML',
            'updated_at' => now(),
        ]);

        DB::table('modulos')->where('url', 'modelosmlsuite')->update([
            'name' => 'Suite MLOps',
            'updated_at' => now(),
        ]);
    }
};
