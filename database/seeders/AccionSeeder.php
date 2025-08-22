<?php

namespace Database\Seeders;

use App\Models\Accion;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class AccionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $acciones = [
            [
                'accion'        => 'Nuevo',
                'name'          => 'new',
                'icon'          => 'Plus',
                'type'          => 'primary',
                'name_funcion'  => 'handleNew',
                'location'      => 'M'
            ],
            [
                'accion'        => 'Editar',
                'name'          => 'edit',
                'icon'          => 'EditPen',
                'type'          => 'warning',
                'name_funcion'  => 'handleEdit'
            ],
            [
                'accion'        => 'Eliminar',
                'name'          => 'delete',
                'icon'          => 'Delete',
                'type'          => 'danger',
                'name_funcion'  => 'handleDelete'
            ]
        ];

        foreach ($acciones as $accion) {
            Accion::firstOrCreate(['name' => $accion['name']], $accion);
        }
    }
}
