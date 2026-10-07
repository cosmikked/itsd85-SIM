<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateGradingDeadlinesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('updateDeadlines', $this->route('academic_term'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'midterm_grading_deadline' => ['sometimes', 'required', 'date'],
            'final_grading_deadline' => ['sometimes', 'required', 'date', 'after_or_equal:midterm_grading_deadline'],
            'inc_completion_deadline' => ['sometimes', 'required', 'date', 'after_or_equal:final_grading_deadline'],
        ];
    }
}
