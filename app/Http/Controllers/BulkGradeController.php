<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBulkGradeRequest;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Services\GradeCalculatorService;
use Illuminate\Support\Facades\DB;

class BulkGradeController extends Controller
{
    public function __construct(private GradeCalculatorService $gradeService) {}

    public function update(UpdateBulkGradeRequest $request, CourseOffering $courseOffering)
    {
        $data = $request->validated();
        $term = $courseOffering->academicTerm;
        $now = now();

        DB::transaction(function () use ($data, $courseOffering, $term, $now) {
            foreach ($data['grades'] as $gradeData) {
                // Ensure enrollment belongs to this course offering
                $enrollment = Enrollment::where('id', $gradeData['enrollment_id'])
                    ->where('course_offering_id', $courseOffering->id)
                    ->first();

                if (! $enrollment) {
                    abort(422, "Enrollment {$gradeData['enrollment_id']} is invalid for this course offering.");
                }

                if (! in_array($enrollment->status, ['enrolled', 'active'])) {
                    abort(422, "Cannot grade inactive enrollment {$gradeData['enrollment_id']}.");
                }

                if (array_key_exists('midterm_raw_score', $gradeData) && $term->midterm_grading_deadline && clone $now > clone $term->midterm_grading_deadline) {
                    abort(403, 'Midterm grading deadline has passed.');
                }
                if (array_key_exists('finalterm_raw_score', $gradeData) && $term->final_grading_deadline && clone $now > clone $term->final_grading_deadline) {
                    abort(403, 'Final grading deadline has passed.');
                }

                $grade = Grade::firstOrNew(['enrollment_id' => $enrollment->id]);
                $grade->setRelation('enrollment', $enrollment);
                $enrollment->setRelation('courseOffering', $courseOffering);
                $courseOffering->setRelation('academicTerm', $term);

                $grade = $this->gradeService->computeGrade($grade, $gradeData);
                $grade->save();
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Grades bulk updated successfully.',
        ]);
    }
}
