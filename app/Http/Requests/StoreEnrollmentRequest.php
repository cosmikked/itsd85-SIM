<?php

namespace App\Http\Requests;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEnrollmentRequest extends FormRequest
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
        $offering = CourseOffering::find($this->course_offering_id);
        $startDate = $offering?->academicTerm?->start_date;

        // Only apply the after_or_equal rule if a valid start date exists
        $dateRule = $startDate ? 'after_or_equal:'.$startDate->toDateString() : 'date_format:Y-m-d';

        return [
            'student_id' => [
                'required',
                'exists:students,id',
            ],
            'course_offering_id' => [
                'required',
                'exists:course_offerings,id',
            ],
            'enrollment_date' => [
                'required',
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
            'student_id.unique' => 'Student has already been enrolled in this course offering.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($this->student_id && $this->course_offering_id) {

                $alreadyEnrolled = Enrollment::where('student_id', $this->student_id)
                    ->where('course_offering_id', $this->course_offering_id)
                    ->exists();

                // 1. DUPLICATE ENROLLMENT
                if ($alreadyEnrolled) {
                    $message = 'Student has already been enrolled in this course.';

                    $validator->errors()->add('student_id', $message);
                    $validator->errors()->add('course_offering_id', $message);
                }

                // 2. COURSE ALREADY FULL
                $courseOffering = CourseOffering::find($this->course_offering_id);
                $courseFull = Enrollment::where('course_offering_id', $this->course_offering_id)
                    ->where('status', 'enrolled')
                    ->count() >= $courseOffering->capacity;

                if ($courseFull) {
                    $validator->errors()->add('course_offering_id', 'Course offering capacity is already full.');
                }

                // 3. COURSE OFFERING STATUS NOT OPEN
                if (! ($courseOffering->status === 'open')) {
                    $validator->errors()->add('course_offering_id', 'Course offering status is currently not open for enrollment.');
                }
            }
        });
    }
}
