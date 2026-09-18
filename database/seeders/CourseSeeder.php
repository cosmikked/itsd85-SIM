<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // CourseFactory's fixed catalog has exactly 26 entries — seed all of them.
        Course::factory()->count(26)->create();
    }
}
