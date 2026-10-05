<?php

namespace App\Services;

use App\Models\Grade;
use App\Models\GradeScale;

class GradeCalculatorService
{
    public function computeGrade(Grade $grade, array $data): Grade
    {
        // 1. Assign raw scores and INC flag from $data
        if (array_key_exists('midterm_raw_score', $data)) {
            $grade->midterm_raw_score = $data['midterm_raw_score'];
        }
        if (array_key_exists('finalterm_raw_score', $data)) {
            $grade->finalterm_raw_score = $data['finalterm_raw_score'];
        }
        if (array_key_exists('re_exam_raw_score', $data)) {
            $grade->re_exam_raw_score = $data['re_exam_raw_score'];
        }
        if (array_key_exists('is_inc', $data)) {
            $grade->is_inc = $this->applyIncState($grade, clone $grade, $data['is_inc']);
        }

        // 2. Lookup Midterm/Final equivalent grades
        $grade->midterm_equivalent_grade = $this->getEquivalentGrade($grade->midterm_raw_score);
        $grade->finalterm_equivalent_grade = $this->getEquivalentGrade($grade->finalterm_raw_score);

        // 3. Compute final_raw_score
        if ($grade->midterm_raw_score !== null && $grade->finalterm_raw_score !== null) {
            $grade->final_raw_score = round(($grade->midterm_raw_score * (1 / 3)) + ($grade->finalterm_raw_score * (2 / 3)), 2);
            $grade->final_equivalent_grade = $this->getEquivalentGrade($grade->final_raw_score);
        } else {
            $grade->final_raw_score = null;
            $grade->final_equivalent_grade = null;
        }

        // 4. Handle Re-exam logic (if eligible: FT == 4.0)
        if ($grade->re_exam_raw_score !== null && $grade->final_equivalent_grade == 4.0) {
            $grade->re_exam_equivalent_grade = $grade->re_exam_raw_score >= 50 ? 3.0 : 5.0;
        } else {
            $grade->re_exam_raw_score = null;
            $grade->re_exam_equivalent_grade = null;
        }

        // 5. Determine and assign Unified Remarks
        $grade->remarks = $this->determineRemarks($grade);

        return $grade;
    }

    private function applyIncState(Grade $grade, Grade $original, bool $isInc): bool
    {
        if ($isInc && ! $original->is_inc) {
            $grade->loadMissing('enrollment.courseOffering.academicTerm');
            $term = $grade->enrollment->courseOffering->academicTerm;
            $grade->inc_expiration_date = $term->inc_completion_deadline;

            return true;
        }
        if (! $isInc) {
            $grade->inc_expiration_date = null;

            return false;
        }

        return $original->is_inc;
    }

    private function getEquivalentGrade(?float $rawScore): ?float
    {
        if ($rawScore === null) {
            return null;
        }

        $scale = GradeScale::where('min_score', '<=', $rawScore)
            ->where('max_score', '>=', $rawScore)
            ->first();

        return $scale ? (float) $scale->grade_point : null;
    }

    public function determineRemarks(Grade $grade): string
    {
        $grade->loadMissing('enrollment');
        $enrollmentStatus = $grade->enrollment->status ?? 'enrolled';

        if ($enrollmentStatus === 'dropped') {
            return $grade->remarks ?? 'Withdrawn';
        }

        if ($grade->is_inc) {
            return 'Incomplete';
        }

        if ($grade->re_exam_equivalent_grade !== null) {
            return $grade->re_exam_equivalent_grade <= 3.0 ? 'Passed' : 'Failed';
        }

        if ($grade->final_equivalent_grade !== null) {
            $eq = (float) $grade->final_equivalent_grade;
            if ($eq <= 3.0) {
                return 'Passed';
            }
            if ($eq == 4.0) {
                return 'Conditional';
            }
            if ($eq == 5.0) {
                return 'Failed';
            }
        }

        // According to business rules: If missing MT or FT, remark is INCOMPLETE
        // Wait, "If a raw score is missing, it's INCOMPLETE" (grades.md)
        return 'Incomplete';
    }
}
