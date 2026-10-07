<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseOfferingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'academic_term_id' => $this->academic_term_id,
            'instructor_id' => $this->instructor_id,
            'section' => $this->section,
            'capacity' => $this->capacity,
            'status' => $this->status,
            'schedules' => $this->schedules->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'room_id' => $schedule->room_id,
                    'day_of_week' => $schedule->day_of_week,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
