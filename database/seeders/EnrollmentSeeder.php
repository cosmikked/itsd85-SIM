<?php

namespace Database\Seeders;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Database\Seeder;

class EnrollmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $studentIds = Student::pluck('id')->all();
        $courseOfferingIds = CourseOffering::pluck('id')->all();

        // enrollments has a unique constraint on (student_id,
        // course_offering_id). Build every valid pair up front and shuffle,
        // so picking from the front of the list can never collide — avoids
        // the naive "random pick per row" approach, which risks a raw
        // QueryException mid-seed as the pair count grows.
        $pairs = [];

        foreach ($studentIds as $studentId) {
            foreach ($courseOfferingIds as $courseOfferingId) {
                $pairs[] = [
                    'student_id' => $studentId,
                    'course_offering_id' => $courseOfferingId,
                ];
            }
        }

        shuffle($pairs);

        foreach (array_slice($pairs, 0, 300) as $pair) {
            Enrollment::factory()->create($pair);
        }
    }
}
