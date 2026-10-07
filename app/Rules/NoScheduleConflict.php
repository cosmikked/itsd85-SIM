<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class NoScheduleConflict implements ValidationRule
{
    public function __construct(
        protected int $academicTermId,
        protected int $instructorId,
        protected ?int $ignoreCourseOfferingId = null
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        // First check internal conflicts in the payload itself
        foreach ($value as $i => $s1) {
            foreach ($value as $j => $s2) {
                if ($i >= $j) {
                    continue;
                }

                // Ensure required keys exist before checking
                if (! isset($s1['day_of_week'], $s1['start_time'], $s1['end_time'], $s1['room_id'])) {
                    continue;
                }
                if (! isset($s2['day_of_week'], $s2['start_time'], $s2['end_time'], $s2['room_id'])) {
                    continue;
                }

                if ($s1['day_of_week'] !== $s2['day_of_week']) {
                    continue;
                }

                if ($this->timesOverlap($s1['start_time'], $s1['end_time'], $s2['start_time'], $s2['end_time'])) {
                    if ($s1['room_id'] == $s2['room_id']) {
                        $fail('The schedules array contains internal room conflicts.');

                        return;
                    }
                    // Instructor is the same for the whole offering, so any overlap is an instructor conflict
                    $fail('The schedules array contains internal instructor conflicts.');

                    return;
                }
            }
        }

        // Now check against database
        foreach ($value as $schedule) {
            if (! isset($schedule['day_of_week'], $schedule['start_time'], $schedule['end_time'], $schedule['room_id'])) {
                continue;
            }

            $query = DB::table('course_offering_schedules')
                ->join('course_offerings', 'course_offering_schedules.course_offering_id', '=', 'course_offerings.id')
                ->where('course_offerings.academic_term_id', $this->academicTermId)
                ->whereNull('course_offerings.deleted_at')
                ->where('course_offering_schedules.day_of_week', $schedule['day_of_week'])
                ->where(function ($q) use ($schedule) {
                    $q->where('course_offering_schedules.start_time', '<', $schedule['end_time'])
                        ->where('course_offering_schedules.end_time', '>', $schedule['start_time']);
                });

            if ($this->ignoreCourseOfferingId) {
                $query->where('course_offering_schedules.course_offering_id', '!=', $this->ignoreCourseOfferingId);
            }

            $conflicts = $query->get(['room_id', 'instructor_id']);

            foreach ($conflicts as $conflict) {
                if ($conflict->room_id == $schedule['room_id']) {
                    $fail("Room {$schedule['room_id']} is already booked during this time.");

                    return;
                }
                if ($conflict->instructor_id == $this->instructorId) {
                    $fail("Instructor {$this->instructorId} is already teaching during this time.");

                    return;
                }
            }
        }
    }

    protected function timesOverlap(string $start1, string $end1, string $start2, string $end2): bool
    {
        return $start1 < $end2 && $end1 > $start2;
    }
}
