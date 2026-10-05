<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<AcademicTerm>
 */
class AcademicTermFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startYear = fake()->unique()->numberBetween(2020, 2026);
        $startDate = Carbon::create($startYear, 8, 1)->addDays(fake()->numberBetween(0, 14));
        $endDate = $startDate->copy()->addMonths(4);

        return [
            'academic_year' => sprintf('%d-%d', $startYear, $startYear + 1),
            'term' => fake()->randomElement(['First Semester', 'Second Semester', 'MidYear']),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => fake()->randomElement(['active', 'inactive']),
            'midterm_grading_deadline' => $startDate->copy()->addMonths(2),
            'final_grading_deadline' => $endDate->copy()->addDays(14),
            'inc_completion_deadline' => $endDate->copy()->addYear(),
        ];
    }
}
