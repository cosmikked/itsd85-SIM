<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->administrator()->create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => 'password',
        ]);

        User::factory()->registrar()->create([
            'name' => 'Registrar',
            'email' => 'registrar@gmail.com',
            'password' => 'password',
        ]);

        User::factory()->instructor()->create([
            'name' => 'Instructor',
            'email' => 'instructor@gmail.com',
            'password' => 'password',
        ]);

        User::factory()->student()->create([
            'name' => 'Student',
            'email' => 'student@gmail.com',
            'password' => 'password',
        ]);

        // Extra instructors so course offerings aren't all taught by the one
        // demo account, plus a couple more staff accounts for headroom.
        User::factory()->instructor()->count(5)->create();
        User::factory()->registrar()->count(2)->create();
        User::factory()->administrator()->count(1)->create();
    }
}
