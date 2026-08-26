<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parent = DB::table('modulos')->where('url', 'modelosml')->first();
        if (! $parent) {
            return;
        }

        $now = now();
        $moduleId = DB::table('modulos')->where('url', 'alarmasmodelo')->value('id');
        $moduleValues = [
            'sistema_id' => $parent->sistema_id,
            'name' => 'Alarmas de modelos',
            'cod_father' => $parent->id,
            'icon' => 'Bell',
            'order' => (int) DB::table('modulos')->where('cod_father', $parent->id)->max('order') + 1,
            'active' => true,
            'deleted_at' => null,
            'updated_at' => $now,
        ];

        if ($moduleId) {
            DB::table('modulos')->where('id', $moduleId)->update($moduleValues);
        } else {
            $moduleId = DB::table('modulos')->insertGetId([
                ...$moduleValues,
                'url' => 'alarmasmodelo',
                'created_at' => $now,
            ]);
        }

        $permissionId = DB::table('permissions')
            ->where('name', 'alarmasmodelo')
            ->where('guard_name', 'web')
            ->value('id');
        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'alarmasmodelo',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $parentPermissionId = DB::table('permissions')
            ->where('name', 'modelosml')
            ->where('guard_name', 'web')
            ->value('id');
        if ($parentPermissionId) {
            DB::table('role_has_permissions')
                ->where('permission_id', $parentPermissionId)
                ->pluck('role_id')
                ->each(fn ($roleId) => DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]));
        }

        app('cache')->forget('spatie.permission.cache');
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'alarmasmodelo')
            ->where('guard_name', 'web')
            ->value('id');
        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        DB::table('modulos')->where('url', 'alarmasmodelo')->delete();
        app('cache')->forget('spatie.permission.cache');
    }
};
