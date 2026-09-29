<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Accion;
use App\Models\Sistema;
use Illuminate\Database\Seeder;
use App\Helpers\PermissionHelper;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

class SistemaModuloAccionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Cargar e insertar Acciones maestras desde el archivo externo
        $accionesBase = require database_path('seeders/data/acciones.php');

        $acciones = collect($accionesBase)->map(fn($a) =>
            Accion::updateOrCreate(['name' => $a['name']], $a)
        );

        // 2. Cargar Sistemas, Módulos y Acciones desde el archivo externo
        $sistemas = require database_path('seeders/data/sistemas_modulos.php');

        // 3. Recorrer cada sistema
        foreach ($sistemas as $sistemaData) {
            $modulosData = $sistemaData['modulos'];
            unset($sistemaData['modulos']);

            $sistema = Sistema::updateOrCreate(
                ['name' => $sistemaData['name']],
                $sistemaData
            );

            foreach ($modulosData as $mod) {
                $accionesMod = $mod['acciones'] ?? [];
                $hijos = $mod['modulos_hijos'] ?? [];

                $accionesIds = $acciones
                    ->whereIn('name', $accionesMod)
                    ->pluck('id')
                    ->toArray();

                // Crear o actualizar el Módulo Padre
                $moduloPadre = $sistema->modulos()->updateOrCreate(
                    ['url' => $mod['url']],
                    collect($mod)->except(['acciones', 'modulos_hijos'])->toArray()
                );

                $moduloPadre->acciones()->sync($accionesIds);

                // Procesar submódulos (hijos) si existen
                foreach ($hijos as $hijoData) {
                    $accionesHijoIds = $acciones
                        ->whereIn('name', $hijoData['acciones'] ?? [])
                        ->pluck('id')
                        ->toArray();

                    $moduloHijo = $sistema->modulos()->updateOrCreate(
                        ['url' => $hijoData['url']],
                        array_merge(
                            collect($hijoData)->except('acciones')->toArray(),
                            ['cod_father' => $moduloPadre->id]
                        )
                    );

                    $moduloHijo->acciones()->sync($accionesHijoIds);
                }
            }
        }

        // 4. Crear Permisos
        PermissionHelper::syncPermisosDesdeAccionModulo();

        // 5. Asignar Permisos (con exclusión configurable de módulos)
        $modulosExcluidos = [
            'piscina',
        ];

        $permissions = Permission::where(function ($query) use ($modulosExcluidos) {
            foreach ($modulosExcluidos as $modulo) {
                $query->where('name', 'not like', $modulo)
                      ->where('name', 'not like', $modulo . '.%');
            }
        })
        ->pluck('name')
        ->toArray();

        $role = Role::firstOrCreate(['name' => 'Administrador']);
        $role->syncPermissions($permissions);

        // 6. Crear usuario y asignar rol
        User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('12345678'),
            ]
        )->assignRole(Role::findByName('Administrador'));
    }
}
