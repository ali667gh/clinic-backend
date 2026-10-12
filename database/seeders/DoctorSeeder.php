<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DoctorSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
        ]);
    }
}
