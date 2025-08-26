<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UbigeoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // DEPARTAMENTOS
        $departamentos = json_decode(File::get(database_path('data/departamentos.json')), true);

        foreach ($departamentos as $dep) {
            DB::table('ubigeo_departamentos')->updateOrInsert(
                ['id' => $dep['id']],
                [
                    'name' => $dep['name'],
                    'code' => $dep['code']
                ]
            );
        }

        // PROVINCIAS
        $provincias = json_decode(File::get(database_path('data/provincias.json')), true);
        foreach ($provincias as $prov) {
            DB::table('ubigeo_provincias')->updateOrInsert(
                ['id' => $prov['id']],
                [
                    'name' => $prov['name'],
                    'departamento_id' => $prov['department_id']
                ]
            );
        }

        // DISTRITOS
        $distritos = json_decode(File::get(database_path('data/distritos.json')), true);
        foreach ($distritos as $dist) {
            DB::table('ubigeo_distritos')->updateOrInsert(
                ['id' => $dist['id']],
                [
                    'name' => $dist['name'],
                    'provincia_id' => $dist['province_id'],
                    'departamento_id' => $dist['department_id']
                ]
            );
        }

    }
}
