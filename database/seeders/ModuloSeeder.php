<?php

namespace Database\Seeders;

use App\Models\Accion;
use App\Models\Modulo;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ModuloSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $acciones = Accion::all();
        $accionesIds = collect($acciones)->pluck('id')->toArray();

        $modulos = [
            [
                'sistema_id' => 1,
                'name' => 'Gestionar Usuarios',
                'url' => 'usuario',
                'icon' => 'UserFilled',
                'order' => 1,
            ],
            [
                'sistema_id' => 1,
                'name' => 'Gestionar Roles',
                'url' => 'role',
                'icon' => 'Switch',
                'order' => 2,
            ],
            [
                'sistema_id' => 1,
                'name' => 'Gestionar Módulos',
                'url' => 'modulo',
                'icon' => 'List',
                'order' => 3,
            ],
            [
                'sistema_id' => 1,
                'name' => 'Gestionar Sistemas',
                'url' => 'sistema',
                'icon' => 'Platform',
                'order' => 4,
            ],
            [
                'sistema_id' => 1,
                'name' => 'Gestionar Acciones',
                'url' => 'accion',
                'icon' => 'TurnOff',
                'order' => 5,
            ],
        ];

        foreach ($modulos as $modulo) {
            $register = Modulo::firstOrCreate(['url' => $modulo['url']], $modulo);
            $register->acciones()->sync($accionesIds);
        }
    }
}
