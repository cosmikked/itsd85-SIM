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
            'midterm_equivalent_grade' => null,
            'finalterm_raw_score' => null,
            'finalterm_equivalent_grade' => null,
            'final_raw_score' => null,
            'final_equivalent_grade' => null,
            're_exam_raw_score' => null,
            're_exam_equivalent_grade' => null,
            'is_inc' => false,
            'inc_expiration_date' => null,
            'remarks' => null,
            'midterm_status' => 'draft',
            'midterm_published_at' => null,
            'final_status' => 'draft',
            'final_published_at' => null,
        ];
    }

    public function midtermPublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'midterm_status' => 'published',
            'midterm_published_at' => now(),
        ]);
    }

    public function finalPublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'final_status' => 'published',
            'final_published_at' => now(),
        ]);
    }

    public function published(): static
    {
        return $this->midtermPublished()->finalPublished();
    }
}
