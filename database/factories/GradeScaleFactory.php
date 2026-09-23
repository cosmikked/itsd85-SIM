<?php

namespace Database\Factories;

use App\Models\GradeScale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeScale>
 */
class GradeScaleFactory extends Factory
{
    /**
     * Fixed, non-overlapping score bands covering 0-100. grade_point is unique
     * per row (matches the DB constraint), so states below pick from a filtered
     * subset rather than generating arbitrary random ranges.
     *
     * @var array<int, array{min_score: float, max_score: float, grade_point: float}>
     */
    private const BANDS = [
        ['min_score' => 94.00, 'max_score' => 100.00, 'grade_point' => 1.00],
        ['min_score' => 88.00, 'max_score' => 93.99, 'grade_point' => 1.25],
        ['min_score' => 82.00, 'max_score' => 87.99, 'grade_point' => 1.50],
        ['min_score' => 76.00, 'max_score' => 81.99, 'grade_point' => 1.75],
        ['min_score' => 70.00, 'max_score' => 75.99, 'grade_point' => 2.00],
        ['min_score' => 64.00, 'max_score' => 69.99, 'grade_point' => 2.25],
        ['min_score' => 58.00, 'max_score' => 63.99, 'grade_point' => 2.50],
        ['min_score' => 52.00, 'max_score' => 57.99, 'grade_point' => 2.75],
        ['min_score' => 50.00, 'max_score' => 51.99, 'grade_point' => 3.00],
        ['min_score' => 30.00, 'max_score' => 49.99, 'grade_point' => 4.00],
        ['min_score' => 0.00, 'max_score' => 29.99, 'grade_point' => 5.00],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return fake()->unique()->randomElement(self::BANDS);
    }
}
