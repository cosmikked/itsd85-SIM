<?php

namespace App\Http\Requests;

use App\Models\GradeScale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enrollment_id' => 'required|exists:enrollments,id|unique:grades,enrollment_id',
            'midterm_raw_score' => 'nullable|numeric|min:0|max:100',
            'finalterm_raw_score' => 'nullable|numeric|min:0|max:100',
            're_exam_raw_score' => 'nullable|numeric|min:0|max:100',
            'is_inc' => 'boolean',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $mtScore = $this->input('midterm_raw_score');
                $ftScore = $this->input('finalterm_raw_score');

                if ($this->filled('finalterm_raw_score') && $mtScore === null) {
                    $validator->errors()->add('finalterm_raw_score', 'Midterm grade must be recorded before final grade.');
                }

                if ($this->filled('re_exam_raw_score')) {
                    if ($mtScore === null || $ftScore === null) {
                        $validator->errors()->add('re_exam_raw_score', 'Re-exam requires both midterm and final grades.');
                    }

                    if ($mtScore !== null && $ftScore !== null) {
                        $finalRaw = round(($mtScore * (1 / 3)) + ($ftScore * (2 / 3)), 2);
                        $scale = GradeScale::where('min_score', '<=', $finalRaw)->where('max_score', '>=', $finalRaw)->first();

                        if (! $scale || (float) $scale->grade_point !== 4.0) {
                            $validator->errors()->add('re_exam_raw_score', 'Re-exam is only allowed if the final equivalent grade is exactly 4.0.');
                        }
                    }
                }
            },
        ];
    }
}
