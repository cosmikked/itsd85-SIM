<?php

namespace Database\Seeders;

use App\Models\Program;
use App\Models\Student;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $programIds = Program::pluck('id');

        foreach (range(1, 150) as $i) {
            Student::factory()->create([
                'program_id' => $programIds->random(),
            ]);
        }
    }
}
