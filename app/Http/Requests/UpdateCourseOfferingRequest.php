<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateCourseOfferingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('course_offering'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'instructor_id' => ['sometimes', 'integer', 'exists:users,id'],
            'section' => [
                'sometimes',
                'string',
                Rule::unique('course_offerings')->where(function ($query) {
                    return $query->where('course_id', $this->course_offering->course_id)
                        ->where('academic_term_id', $this->course_offering->academic_term_id);
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
                $currentEnrollments = $this->course_offering->enrollments()->where('status', 'enrolled')->count();
                if ($this->capacity < $currentEnrollments) {
                    $validator->errors()->add('capacity', "Capacity cannot be lower than the current number of enrolled students ({$currentEnrollments}).");
                }
            }
        });
    }
}
