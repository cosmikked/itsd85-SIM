<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
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
        $student = $this->route('student');

        return [
            'student_number' => [
                'sometimes', 'required', 'string',
                Rule::unique('students', 'student_number')->ignore($student),
            ],
            'first_name' => ['sometimes', 'required', 'string'],
            'middle_name' => ['nullable', 'string'],
            'last_name' => ['sometimes', 'required', 'string'],
            'suffix' => ['nullable', 'string'],
            'birth_date' => ['sometimes', 'required', 'date', 'before:today'],
            'email' => [
                'sometimes', 'required', 'email',
                Rule::unique('students', 'email')->ignore($student),
            ],
            'contact_number' => [
                'sometimes', 'required', 'string',
                Rule::unique('students', 'contact_number')->ignore($student),
            ],
            'address' => ['nullable', 'string'],
            'program_id' => ['sometimes', 'required', 'integer', 'exists:programs,id'],
            'year_level' => ['sometimes', 'required', 'integer', 'between:1,4'],
            'status' => ['sometimes', 'in:regular,irregular,extendee'],
        ];
    }
}
