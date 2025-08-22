<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Helpers\PermissionHelper;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // CREAR LOS PERMISOS EN LA TABLA PERMISSIONS
        PermissionHelper::syncPermisosDesdeAccionModulo();

        $permissions = Permission::all();
        $permissionsIds = collect($permissions)->pluck('name')->toArray();

        $role = Role::firstOrCreate(['name' => 'Administrador']);
        $role->syncPermissions($permissionsIds);
    }
}
