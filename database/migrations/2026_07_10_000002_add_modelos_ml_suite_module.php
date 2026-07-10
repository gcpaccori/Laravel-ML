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
            DB::table('modulos')->updateOrInsert(
                [
                    'sistema_id' => $sistemaId,
                    'url' => 'modelosmlsuite',
                ],
                [
                    'name' => 'Suite MLOps',
                    'cod_father' => null,
                    'icon' => 'DataAnalysis',
                    'order' => 4,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $permissionId = DB::table('permissions')
            ->where('name', 'modelosmlsuite')
            ->where('guard_name', 'web')
            ->value('id');

        if (!$permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'modelosmlsuite',
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
            ->where('name', 'modelosmlsuite')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        DB::table('modulos')->where('url', 'modelosmlsuite')->delete();
        app('cache')->forget('spatie.permission.cache');
    }
};
