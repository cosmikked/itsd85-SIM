<?php

namespace App\Http\Requests;

use App\Rules\NoScheduleConflict;
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
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', 'in:open,closed,cancelled'],

            'schedules' => [
                'sometimes',
                'array',
                'min:1',
                new NoScheduleConflict(
                    $this->course_offering->academic_term_id,
                    $this->instructor_id ?? $this->course_offering->instructor_id,
                    $this->course_offering->id
                ),
            ],
            'schedules.*.room_id' => ['required_with:schedules', 'integer', 'exists:rooms,id'],
            'schedules.*.day_of_week' => ['required_with:schedules', 'string'],
            'schedules.*.start_time' => ['required_with:schedules', 'date_format:H:i:s'],
            'schedules.*.end_time' => ['required_with:schedules', 'date_format:H:i:s', 'after:schedules.*.start_time'],
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
