<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parent = DB::table('modulos')->where('url', 'modelosml')->first();
        if (!$parent) {
            return;
        }

        $moduleId = DB::table('modulos')->where('url', 'gemelodigital')->value('id');
        if (!$moduleId) {
            $moduleId = DB::table('modulos')->insertGetId([
                'sistema_id' => $parent->sistema_id,
                'name' => 'Gemelo digital de piscina',
                'cod_father' => $parent->id,
                'url' => 'gemelodigital',
                'icon' => 'SetUp',
                'order' => (int) DB::table('modulos')
                    ->where('sistema_id', $parent->sistema_id)
                    ->max('order') + 1,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permissionId = DB::table('permissions')->where('name', 'gemelodigital')->value('id');
        if (!$permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'gemelodigital',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $parentPermissionId = DB::table('permissions')->where('name', 'modelosml')->value('id');
        if (!$parentPermissionId) {
            return;
        }

        $roleIds = DB::table('role_has_permissions')
            ->where('permission_id', $parentPermissionId)
            ->pluck('role_id');
        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'gemelodigital')->value('id');
        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
        DB::table('modulos')->where('url', 'gemelodigital')->delete();
    }
};
