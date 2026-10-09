<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Support\Facades\Gate;

class AcademicRecordController extends Controller
{
    public function show(Student $student)
    {
        Gate::authorize('view', $student);

        $enrollments = $student->enrollments()
            ->with(['courseOffering.academicTerm', 'courseOffering.course', 'grade'])
            ->get();

        $grouped = $enrollments->groupBy(function ($enrollment) {
            $term = $enrollment->courseOffering->academicTerm;

            return $term->academic_year.' '.$term->term;
        });

        $record = $grouped->map(function ($enrollments, $termName) {
            return [
                'academic_term' => $termName,
                'enrollments' => $enrollments->map(function ($enr) {
                    $finalGrade = $enr->grade?->isFinalPublished() ? $enr->grade : null;

                    return [
                        'course_code' => $enr->courseOffering->course->course_code,
                        'course_title' => $enr->courseOffering->course->course_title,
                        'units' => $enr->courseOffering->course->units,
                        // the academic record is official: only published finals count
                        'final_equivalent_grade' => $finalGrade?->final_equivalent_grade,
                        'remarks' => $finalGrade?->remarks ?? ($enr->status === 'dropped' ? 'Withdrawn' : 'Ongoing'),
                    ];
                }),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Academic record retrieved successfully.',
            'data' => $record,
        ]);
    }
}
