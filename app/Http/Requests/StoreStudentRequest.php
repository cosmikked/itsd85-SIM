<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
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
        return [
            'student_number' => ['required', 'string', 'unique:students,student_number'],
            'first_name' => ['required', 'string'],
            'middle_name' => ['nullable', 'string'],
            'last_name' => ['required', 'string'],
            'suffix' => ['nullable', 'string'],
            'birth_date' => ['required', 'date', 'before:today'],
            'email' => ['required', 'email', 'unique:students,email'],
            'contact_number' => ['required', 'string', 'unique:students,contact_number'],
            'address' => ['nullable', 'string'],
            'program_id' => ['required', 'integer', 'exists:programs,id'],
            'year_level' => ['required', 'integer', 'between:1,4'],
            'status' => ['sometimes', 'in:regular,irregular,extendee'],
        ];
    }
}
