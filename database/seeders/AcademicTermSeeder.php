<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use Illuminate\Database\Seeder;

class AcademicTermSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // AcademicTermFactory picks a unique start year from a 7-year range
        // (2020-2026), so 6 stays safely under that ceiling.
        AcademicTerm::factory()->count(6)->create();
    }
}
