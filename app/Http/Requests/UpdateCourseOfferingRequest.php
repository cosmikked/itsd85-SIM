<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseOfferingRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'course_id' => ['sometimes', 'integer', 'exists:courses,id'],
            'academic_term_id' => ['sometimes', 'integer', 'exists:academic_terms,id'],
            'instructor_id' => ['sometimes', 'integer', 'exists:users,id'],
            'section' => [
                'sometimes', 
                'string',
                Rule::unique('course_offerings')->where(function ($query) {
                    return $query->where('course_id', $this->input('course_id', $this->course_offering->course_id))
                                 ->where('academic_term_id', $this->input('academic_term_id', $this->course_offering->academic_term_id));
                })->ignore($this->course_offering),
            ],
            'schedule' => ['sometimes', 'string'],
            'room' => ['nullable', 'string'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', 'in:open,closed,cancelled'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->has('capacity')) {
                $currentEnrollments = $this->course_offering->enrollments()->count();
                if ($this->capacity < $currentEnrollments) {
                    $validator->errors()->add('capacity', "Capacity cannot be lower than the current number of enrolled students ({$currentEnrollments}).");
                }
            }
        });
    }
}
