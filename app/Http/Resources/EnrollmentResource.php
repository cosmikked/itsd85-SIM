<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentResource extends JsonResource
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
            'student_id' => $this->student_id,
            'course_offering_id' => $this->course_offering_id,
            'enrollment_date' => $this->enrollment_date->toDateString(),
            'status' => $this->status,
            // only present when the controller eager-loads them (offering roster)
            'student' => new StudentSummaryResource($this->whenLoaded('student')),
            'grade' => $this->whenLoaded('grade', fn () => $this->gradeVisibleTo($request)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Administrators and instructors see drafts; anyone else only sees a
     * grade once at least one period is published.
     */
    private function gradeVisibleTo(Request $request): ?GradeResource
    {
        $grade = $this->grade;

        if (! $grade) {
            return null;
        }

        $seesDrafts = in_array($request->user()?->role, ['administrator', 'instructor'], true);

        return $seesDrafts || $grade->isAnyPublished() ? new GradeResource($grade) : null;
    }
}
