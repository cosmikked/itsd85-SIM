<?php

namespace Database\Factories;

use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    /**
     * Real degree programs to pick from, so generated data reads like an
     * actual course catalog instead of random words. Codes are all distinct
     * up front, since programs.code is unique in the database.
     *
     * @var array<int, array{code: string, name: string, description: string}>
     */
    private const PROGRAMS = [
        ['code' => 'BSCS', 'name' => 'Bachelor of Science in Computer Science', 'description' => 'Focuses on computing theory, algorithms, and software development.'],
        ['code' => 'BSIT', 'name' => 'Bachelor of Science in Information Technology', 'description' => 'Focuses on the practical application and management of information systems.'],
        ['code' => 'BSIS', 'name' => 'Bachelor of Science in Information Systems', 'description' => 'Bridges business processes with information technology solutions.'],
        ['code' => 'BSA', 'name' => 'Bachelor of Science in Accountancy', 'description' => 'Prepares students for professional accounting practice and CPA licensure.'],
        ['code' => 'BSBA', 'name' => 'Bachelor of Science in Business Administration', 'description' => 'Covers management, marketing, and business operations fundamentals.'],
        ['code' => 'BSN', 'name' => 'Bachelor of Science in Nursing', 'description' => 'Prepares students for professional nursing practice and licensure.'],
        ['code' => 'BSED', 'name' => 'Bachelor of Secondary Education', 'description' => 'Prepares students to teach at the secondary education level.'],
        ['code' => 'BEED', 'name' => 'Bachelor of Elementary Education', 'description' => 'Prepares students to teach at the elementary education level.'],
        ['code' => 'BSCE', 'name' => 'Bachelor of Science in Civil Engineering', 'description' => 'Covers the design, construction, and maintenance of infrastructure.'],
        ['code' => 'BSEE', 'name' => 'Bachelor of Science in Electrical Engineering', 'description' => 'Covers electrical systems, power, and electronics design.'],
        ['code' => 'BSPSY', 'name' => 'Bachelor of Science in Psychology', 'description' => 'Studies human behavior and mental processes.'],
        ['code' => 'BSHM', 'name' => 'Bachelor of Science in Hospitality Management', 'description' => 'Prepares students for careers in hotel and hospitality operations.'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $program = fake()->unique()->randomElement(self::PROGRAMS);

        return [
            'code' => $program['code'],
            'name' => $program['name'],
            'description' => $program['description'],
            'status' => fake()->randomElement(['active', 'inactive']),
        ];
    }
}
