<?php

namespace Database\Factories;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * enrollment_date is a placeholder here — configure() below overwrites it
     * once the enrollment's actual course_offering_id is known (default or
     * explicitly overridden by the caller), so the date always falls within
     * that offering's academic term.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'course_offering_id' => CourseOffering::factory(),
            'enrollment_date' => now(),
            'status' => fake()->randomElement(['enrolled', 'dropped', 'completed']),
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Enrollment $enrollment): void {
            $academicTerm = $enrollment->courseOffering->academicTerm;

            $enrollment->forceFill([
                'enrollment_date' => fake()->dateTimeBetween(
                    $academicTerm->start_date,
                    $academicTerm->end_date
                ),
            ])->save();
        });
    }
}
