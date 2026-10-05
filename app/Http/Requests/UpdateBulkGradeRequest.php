<?php

namespace App\Http\Requests;

use App\Models\Grade;
use App\Models\GradeScale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateBulkGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grades' => 'required|array',
            'grades.*.enrollment_id' => 'required|exists:enrollments,id',
            'grades.*.midterm_raw_score' => 'nullable|numeric|min:0|max:100',
            'grades.*.finalterm_raw_score' => 'nullable|numeric|min:0|max:100',
            'grades.*.re_exam_raw_score' => 'nullable|numeric|min:0|max:100',
            'grades.*.is_inc' => 'boolean',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $gradesData = $this->input('grades', []);

                foreach ($gradesData as $index => $data) {
                    // Fetch existing grade if any
                    $grade = Grade::where('enrollment_id', $data['enrollment_id'] ?? null)->first();

                    $mtScore = $data['midterm_raw_score'] ?? $grade?->midterm_raw_score;
                    $ftScore = $data['finalterm_raw_score'] ?? $grade?->finalterm_raw_score;

                    if (array_key_exists('finalterm_raw_score', $data) && $data['finalterm_raw_score'] !== null && $mtScore === null) {
                        $validator->errors()->add("grades.$index.finalterm_raw_score", 'Midterm grade must be recorded before final grade.');
                    }

                    if (array_key_exists('re_exam_raw_score', $data) && $data['re_exam_raw_score'] !== null) {
                        if ($mtScore === null || $ftScore === null) {
                            $validator->errors()->add("grades.$index.re_exam_raw_score", 'Re-exam requires both midterm and final grades.');
                        }

                        if ($mtScore !== null && $ftScore !== null) {
                            $finalRaw = round(($mtScore * (1 / 3)) + ($ftScore * (2 / 3)), 2);
                            $scale = GradeScale::where('min_score', '<=', $finalRaw)->where('max_score', '>=', $finalRaw)->first();

                            if (! $scale || (float) $scale->grade_point !== 4.0) {
                                $validator->errors()->add("grades.$index.re_exam_raw_score", 'Re-exam is only allowed if the final equivalent grade is exactly 4.0.');
                            }
                        }
                    }
                }
            },
        ];
    }
}
