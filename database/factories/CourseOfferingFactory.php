<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseOffering>
 */
class CourseOfferingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'academic_term_id' => AcademicTerm::factory(),
            'instructor_id' => User::factory()->instructor(),
            'section' => fake()->randomElement(['A', 'B', 'C', 'D', 'E']),
            'capacity' => fake()->numberBetween(20, 50),
            'status' => fake()->randomElement(['open', 'closed', 'cancelled']),
        ];
    }
}
