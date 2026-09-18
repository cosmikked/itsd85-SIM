<?php

namespace Database\Seeders;

use App\Models\Program;
use Illuminate\Database\Seeder;

class ProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ProgramFactory's fixed catalog has exactly 12 entries — seed all of them.
        Program::factory()->count(12)->create();
    }
}
