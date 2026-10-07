<?php

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('RM-###'),
            'building' => fake()->randomElement(['Science Hall', 'Arts Bldg', 'Main Campus']),
            'capacity' => fake()->numberBetween(20, 100),
        ];
    }
}
