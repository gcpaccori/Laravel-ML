<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call([
            SistemaSeeder::class,
            AccionSeeder::class,
            ModuloSeeder::class,
            PermissionSeeder::class,
            UserSeeder::class,
            UbigeoSeeder::class,
            ParametroBandaSeeder::class,
        ]);
    }
}
