<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\User;
use Illuminate\Database\Seeder;

class CourseOfferingSeeder extends Seeder
{
    private const SECTIONS = ['A', 'B', 'C', 'D', 'E'];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $courseIds = Course::pluck('id')->all();
        $academicTermIds = AcademicTerm::pluck('id')->all();
        $instructorIds = User::where('role', 'instructor')->pluck('id')->all();

        // course_offerings has a unique constraint on (course_id,
        // academic_term_id, section). Build every valid combination up front
        // and shuffle, so picking from the front of the list can never
        // collide — same reasoning as EnrollmentSeeder below.
        $combinations = [];

        foreach ($courseIds as $courseId) {
            foreach ($academicTermIds as $academicTermId) {
                foreach (self::SECTIONS as $section) {
                    $combinations[] = [
                        'course_id' => $courseId,
                        'academic_term_id' => $academicTermId,
                        'section' => $section,
                    ];
                }
            }
        }

        shuffle($combinations);

        foreach (array_slice($combinations, 0, 40) as $combination) {
            CourseOffering::factory()->create([
                ...$combination,
                'instructor_id' => fake()->randomElement($instructorIds),
            ]);
        }
    }
}
