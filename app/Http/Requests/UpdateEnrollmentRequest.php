<?php

namespace App\Http\Requests;

use App\Models\Enrollment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEnrollmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('enrollment'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // returns the Enrollment model specified in the URL
        $enrollment = $this->route('enrollment');

        $startDate = $enrollment?->courseOffering?->academicTerm?->start_date;
        $dateRule = $startDate ? 'after_or_equal:'.$startDate->toDateString() : 'date_format:Y-m-d';

        return [
            'enrollment_date' => [
                'sometimes',
                'date_format:Y-m-d',
                $dateRule,
            ],
            'status' => [
                'sometimes',
                Rule::in(['enrolled', 'dropped', 'completed']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'enrollment_date.after_or_equal' => 'Enrollment date must be on or after the start of the associated academic term.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $enrollment = $this->route('enrollment');

            if ($this->has('status') && $this->status === 'enrolled' && $enrollment?->status !== 'enrolled') {
                $offering = $enrollment->courseOffering;

                $currentSeatsTaken = Enrollment::where('course_offering_id', $offering->id)
                    ->where('status', 'enrolled')
                    ->count();

                if ($currentSeatsTaken >= $offering->capacity) {
                    $validator->errors()->add('status', 'Cannot re-enroll student. The course offering is already at full capacity.');
                }
            }
        });
    }
}
