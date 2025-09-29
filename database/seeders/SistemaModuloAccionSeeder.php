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
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class SistemaModuloAccionSeeder extends Seeder
{

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Acciones maestras (se crean una vez)
        $accionesBase = [
            [
                'accion'       => 'Nuevo',
                'name'         => 'new',
                'icon'         => 'Plus',
                'type'         => 'primary',
                'name_funcion' => 'handleNew',
                'location'     => 'M',
            ],
            [
                'accion'       => 'Editar',
                'name'         => 'edit',
                'icon'         => 'EditPen',
                'type'         => 'warning',
                'name_funcion' => 'handleEdit',
            ],
            [
                'accion'       => 'Eliminar',
                'name'         => 'delete',
                'icon'         => 'Delete',
                'type'         => 'danger',
                'name_funcion' => 'handleDelete',
            ],
        ];

        $acciones = collect($accionesBase)->map(fn($a) =>
            Accion::updateOrCreate(['name' => $a['name']], $a)
        );

        // 2️. Definir todos los sistemas con módulos y acciones
        $sistemas = [
            [
                'name'    => 'Sistema De Seguridad',
                'icon'    => 'Lock',
                'url'     => 'seguridad',
                'order'   => 10,
                'modulos' => [
                    [
                        'name'     => 'Gestionar Usuarios',
                        'url'      => 'usuario',
                        'icon'     => 'UserFilled',
                        'order'    => 1,
                        'acciones' => ['new','edit','delete'],
                    ],
                    [
                        'name'     => 'Gestionar Roles',
                        'url'      => 'role',
                        'icon'     => 'Switch',
                        'order'    => 2,
                        'acciones' => ['new','edit','delete'],
                    ],
                    [
                        'name'     => 'Gestionar Módulos',
                        'url'      => 'modulo',
                        'icon'     => 'List',
                        'order'    => 3,
                        'acciones' => ['new','edit','delete'],
                    ],
                    [
                        'name'     => 'Gestionar Sistemas',
                        'url'      => 'sistema',
                        'icon'     => 'Platform',
                        'order'    => 4,
                        'acciones' => ['new','edit','delete'],
                    ],
                    [
                        'name'     => 'Gestionar Acciones',
                        'url'      => 'accion',
                        'icon'     => 'TurnOff',
                        'order'    => 5,
                        'acciones' => ['new','edit','delete'],
                    ]
                ],
            ],
            [
                'name'    => 'Panel de Monitoreo',
                'icon'    => 'Monitor',
                'url'     => 'monitoreo',
                'order'   => 1,
                'modulos' => [
                    [
                        'name'     => 'Historial Calidad Agua',
                        'url'      => 'historialagua',
                        'icon'     => 'Stopwatch',
                        'order'    => 1,
                        'acciones' => ['new','edit','delete'],
                    ],
                    [
                        'name'     => 'Calidad de Agua',
                        'url'      => 'calidadagua',
                        'icon'     => 'Stopwatch',
                        'order'    => 1,
                        'acciones' => ['new','edit','delete'],
                    ],
                ]
                ],
            [
                'name'    => 'Gestionar Piscigranjas',
                'icon'    => 'Refrigerator',
                'url'     => 'sispiscis',
                'order'   => 2,
                'modulos' => [
                    [
                        'name'     => 'Especies',
                        'url'      => 'especie',
                        'icon'     => 'Flag',
                        'order'    => 4,
                        'acciones' => ['new','edit','delete'],
                    ],
                    [
                        'name'     => 'Campañas',
                        'url'      => 'campania',
                        'icon'     => 'Calendar',
                        'order'    => 3,
                        'acciones' => ['new','edit','delete'],
                    ],
                    [
                        'name'     => 'Piscinas',
                        'url'      => 'piscina',
                        'icon'     => 'Aim',
                        'order'    => 2,
                        'acciones' => ['new','edit','delete'],
                    ],
                    [
                        'name'     => 'Piscigranjas',
                        'url'      => 'piscigranja',
                        'icon'     => 'Aim',
                        'order'    => 1,
                        'acciones' => ['new','edit','delete'],
                    ],
                ]
                ],
            [
                'name'    => 'Gestionar Producción',
                'icon'    => 'DataLine',
                'url'     => 'produccion',
                'order'   => 3,
                'modulos' => [
                    [
                        'name'     => 'Biometrias',
                        'url'      => 'biometria',
                        'icon'     => 'DataLine',
                        'order'    => 1,
                        'acciones' => ['new','edit','delete'],
                    ],
                ]
            ]
        ];

        // 3️. Recorrer cada sistema
        foreach ($sistemas as $sistemaData) {
            $modulosData = $sistemaData['modulos'];
            unset($sistemaData['modulos']);

            $sistema = Sistema::updateOrCreate(
                ['name' => $sistemaData['name']],
                $sistemaData
            );

            foreach ($modulosData as $mod) {
                $accionesIds = $acciones
                    ->whereIn('name', $mod['acciones'])
                    ->pluck('id')
                    ->toArray();

                $modulo = $sistema->modulos()->updateOrCreate(
                    ['url' => $mod['url']],
                    collect($mod)->except('acciones')->toArray()
                );

                $modulo->acciones()->sync($accionesIds);
            }
        }

        // 4. Crear Permisos
        PermissionHelper::syncPermisosDesdeAccionModulo();

        // 5. Asignar Permisos
        $permissions = Permission::all()->pluck('name')->toArray();
        // $permissionsIds = collect($permissions)->pluck('name')->toArray();

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
