<?php

namespace App\Http\Requests;

use App\Models\AcademicTerm;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCourseOfferingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', App\Models\CourseOffering::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'academic_term_id' => ['required', 'integer', 'exists:academic_terms,id'],
            'instructor_id' => ['required', 'integer', 'exists:users,id'],
            'section' => [
                'required',
                'string',
                Rule::unique('course_offerings')->where(function ($query) {
                    return $query->where('course_id', $this->course_id)
                        ->where('academic_term_id', $this->academic_term_id);
                }),
            ],
            'schedule' => ['required', 'string'],
            'room' => ['nullable', 'string'],
            'capacity' => ['required', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', 'in:open,closed,cancelled'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $term = AcademicTerm::find($this->academic_term_id);
            if ($term && $term->status !== 'active') {
                $validator->errors()->add('academic_term_id', 'The academic term must be active to create a course offering.');
            }
        });
    }
}
