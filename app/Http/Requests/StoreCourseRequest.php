<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCourseRequest extends FormRequest
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
            'course_code' => ['required', 'string', 'unique:courses,course_code'],
            'course_title' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'units' => ['required', 'integer', 'between:1,9'],
            'status' => ['sometimes', 'in:active,inactive'],
        ];
    }
}
