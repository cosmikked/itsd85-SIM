<?php

namespace App\Http\Requests;

use App\Rules\ConsecutiveAcademicYear;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAcademicTermRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $academicYear = $this->input('academic_year');

        return [
            'academic_year' => ['required', 'string', new ConsecutiveAcademicYear],
            'term' => [
                'bail',
                'required',
                Rule::in(['First Semester', 'Second Semester', 'MidYear']),
                Rule::unique('academic_terms', 'term')
                    ->where('academic_year', is_string($academicYear) ? $academicYear : ''),
            ],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date'],
            'status' => ['sometimes', 'in:active,inactive'],
        ];
    }
}
