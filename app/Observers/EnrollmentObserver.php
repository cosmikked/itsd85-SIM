<?php

namespace App\Observers;

use App\Models\Enrollment;
use App\Models\Grade;

class EnrollmentObserver
{
    /**
     * Handle the Enrollment "updated" event.
     */
    public function updated(Enrollment $enrollment): void
    {
        if ($enrollment->wasChanged('status') && $enrollment->status === 'dropped') {
            $grade = Grade::where('enrollment_id', $enrollment->id)->first();

            if (! $grade) {
                // If there's no grade record yet, we can create one with 'Withdrawn' remark
                $grade = new Grade(['enrollment_id' => $enrollment->id]);
            }

            $enrollment->loadMissing('courseOffering.academicTerm');
            $term = $enrollment->courseOffering->academicTerm;
            $now = now();

            // Nullify scores
            $grade->midterm_raw_score = null;
            $grade->finalterm_raw_score = null;
            $grade->final_raw_score = null;
            $grade->final_equivalent_grade = null;
            $grade->re_exam_raw_score = null;
            $grade->re_exam_equivalent_grade = null;

            if ($term->midterm_grading_deadline && $now->gt($term->midterm_grading_deadline)) {
                // Dropped after midterm deadline
                if ($grade->midterm_equivalent_grade !== null && $grade->midterm_equivalent_grade <= 3.0) {
                    $grade->remarks = 'Withdrawn';
                } else {
                    $grade->remarks = 'Failed';
                    $grade->final_equivalent_grade = 5.0;
                }
            } else {
                // Dropped before midterm deadline
                $grade->midterm_equivalent_grade = null;
                $grade->remarks = 'Withdrawn';
            }

            $grade->save();
        }
    }
}
