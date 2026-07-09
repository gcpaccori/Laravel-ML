<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $sistemaId = DB::table('sistemas')->where('url', 'monitoreo')->value('id');

        if ($sistemaId) {
            $exists = DB::table('modulos')
                ->where('sistema_id', $sistemaId)
                ->where('url', 'modelosml')
                ->exists();

            if (!$exists) {
                DB::table('modulos')->insert([
                    'sistema_id' => $sistemaId,
                    'name' => 'Modelos ML',
                    'cod_father' => null,
                    'url' => 'modelosml',
                    'icon' => 'DataAnalysis',
                    'order' => 3,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $permissionId = DB::table('permissions')
            ->where('name', 'modelosml')
            ->where('guard_name', 'web')
            ->value('id');

        if (!$permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'modelosml',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('roles')->pluck('id')->each(function ($roleId) use ($permissionId) {
            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        });

        app('cache')->forget('spatie.permission.cache');
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'modelosml')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        DB::table('modulos')->where('url', 'modelosml')->delete();
        app('cache')->forget('spatie.permission.cache');
    }
};
