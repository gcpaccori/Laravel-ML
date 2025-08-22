<?php

namespace Database\Seeders;

use App\Models\Sistema;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class SistemaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Sistema::firstOrCreate([
            'name' => 'Sistema De Seguridad',
        ], [
            'icon'  => 'Lock',
            'url'   => 'seguridad',
            'order' => 1,
        ]);
    }
}
