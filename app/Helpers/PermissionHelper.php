<?php
// app/Helpers/PermissionHelper.php

namespace App\Helpers;

use App\Models\Modulo;
use App\Models\Accion;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class PermissionHelper
{
    public static function syncPermisosDesdeAccionModulo()
    {
        $permisosValidos = [];

        // 1. Registrar permisos base para TODOS los módulos
        $modulos = Modulo::all();
        foreach ($modulos as $modulo) {
            $moduloUrl = strtolower($modulo->url);

            Permission::firstOrCreate([
                'name' => $moduloUrl,
                'guard_name' => 'web',
            ]);

            $permisosValidos[] = $moduloUrl;
        }

        // 2. Registrar permisos específicos (modulo.accion)
        $accionesModulos = DB::table('accion_modulo')->get();
        foreach ($accionesModulos as $item) {
            $modulo = Modulo::find($item->modulo_id);
            $accion = Accion::find($item->accion_id);

            if ($modulo && $accion) {
                $nombrePermiso = strtolower($modulo->url) . '.' . strtolower($accion->name);

                Permission::firstOrCreate([
                    'name' => $nombrePermiso,
                    'guard_name' => 'web',
                ]);

                $permisosValidos[] = $nombrePermiso;
            }
        }

        // 3. Eliminar permisos que no están en la lista válida
        Permission::where('guard_name', 'web')
            ->whereNotIn('name', $permisosValidos)
            ->delete();
    }
}
