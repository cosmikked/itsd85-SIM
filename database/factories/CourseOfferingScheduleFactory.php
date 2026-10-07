<?php

namespace Database\Factories;

use App\Models\CourseOffering;
use App\Models\CourseOfferingSchedule;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseOfferingSchedule>
 */
class CourseOfferingScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_offering_id' => CourseOffering::factory(),
            'room_id' => Room::factory(),
            'day_of_week' => fake()->randomElement(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']),
            'start_time' => '09:00:00',
            'end_time' => '10:30:00',
        ];
    }
}
