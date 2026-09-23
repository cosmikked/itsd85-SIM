<?php

namespace Database\Factories;

use App\Models\Program;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_number' => fake()->unique()->numerify('####-#####'),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->firstName(),
            'last_name' => fake()->lastName(),
            'suffix' => fake()->optional(0.1)->randomElement(['Jr.', 'Sr.', 'II', 'III']),
            'birth_date' => fake()->dateTimeBetween('-25 years', '-16 years'),
            'email' => fake()->unique()->safeEmail(),
            // numerify() with a fixed digit template, rather than phoneNumber() (whose
            // few locale formats collide well before Faker's unique() retry limit once
            // a test suite creates more than a couple dozen students).
            'contact_number' => fake()->unique()->numerify('09#########'),
            'address' => fake()->optional()->address(),
            'program_id' => Program::factory(),
            'year_level' => fake()->numberBetween(1, 4),
            'status' => fake()->randomElement(['regular', 'irregular', 'extendee']),
        ];
    }
}
