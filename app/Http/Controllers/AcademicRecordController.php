<?php

namespace App\Http\Controllers;

use App\Models\Student;

class AcademicRecordController extends Controller
{
    public function show(Student $student)
    {
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
                    return [
                        'course_code' => $enr->courseOffering->course->course_code,
                        'course_title' => $enr->courseOffering->course->course_title,
                        'units' => $enr->courseOffering->course->units,
                        'final_equivalent_grade' => $enr->grade?->final_equivalent_grade,
                        'remarks' => $enr->grade?->remarks ?? ($enr->status === 'dropped' ? 'Withdrawn' : 'Ongoing'),
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
