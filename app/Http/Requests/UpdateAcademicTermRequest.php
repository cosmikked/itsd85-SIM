<?php

namespace App\Http\Requests;

use App\Models\AcademicTerm;
use App\Rules\ConsecutiveAcademicYear;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicTermRequest extends FormRequest
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
     * A field that is not sent keeps its stored value, so the rules that compare
     * fields (the year and term pair, and the date order) fall back to the stored
     * value for whichever side of the comparison was not sent.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var AcademicTerm $academicTerm */
        $academicTerm = $this->route('academic_term');

        $academicYearRules = ['sometimes', 'required', 'string', new ConsecutiveAcademicYear];
        $termRules = ['bail', 'sometimes', 'required', Rule::in(['First Semester', 'Second Semester', 'MidYear'])];
        $startDateRules = ['sometimes', 'required', 'date_format:Y-m-d'];
        $endDateRules = ['sometimes', 'required', 'date_format:Y-m-d'];

        if ($this->has('term')) {
            $academicYear = $this->input('academic_year', $academicTerm->academic_year);

            $termRules[] = Rule::unique('academic_terms', 'term')
                ->where('academic_year', is_string($academicYear) ? $academicYear : '')
                ->ignore($academicTerm);
        } elseif ($this->has('academic_year')) {
            $academicYearRules[] = Rule::unique('academic_terms', 'academic_year')
                ->where('term', $academicTerm->term)
                ->ignore($academicTerm);
        }

        if ($this->has('start_date') && $this->has('end_date')) {
            $endDateRules[] = 'after:start_date';
        } elseif ($this->has('start_date')) {
            $startDateRules[] = 'before:'.$academicTerm->end_date->toDateString();
        } elseif ($this->has('end_date')) {
            $endDateRules[] = 'after:'.$academicTerm->start_date->toDateString();
        }

        return [
            'academic_year' => $academicYearRules,
            'term' => $termRules,
            'start_date' => $startDateRules,
            'end_date' => $endDateRules,
            'status' => ['sometimes', 'in:active,inactive'],
        ];
    }
}
