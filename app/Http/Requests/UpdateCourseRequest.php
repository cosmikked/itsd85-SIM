<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseRequest extends FormRequest
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
            'course_code' => [
                'sometimes',
                'required',
                'string',
                Rule::unique('courses', 'course_code')->ignore($this->route('course')),
            ],
            'course_title' => ['sometimes', 'required', 'string'],
            'description' => ['nullable', 'string'],
            'units' => ['sometimes', 'required', 'integer', 'between:1,9'],
            'status' => ['sometimes', 'in:active,inactive'],
        ];
    }
}
