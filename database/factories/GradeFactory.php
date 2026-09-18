<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Grade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Both grade-scale links default to null — a Grade row can exist for an
     * enrollment before any score has been submitted or banded.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'midterm_raw_score' => null,
            'midterm_grade_scale_id' => null,
            'final_raw_score' => null,
            'final_grade_scale_id' => null,
            'status' => null,
        ];
    }
}
