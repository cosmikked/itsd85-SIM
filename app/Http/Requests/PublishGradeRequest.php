<?php

namespace App\Http\Requests;

use App\Services\GradePublicationService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PublishGradeRequest extends FormRequest
{
    /**
     * Used by both POST /grades/{grade}/publish and
     * POST /course-offerings/{course_offering}/grades/publish.
     */
    public function authorize(): bool
    {
        if ($grade = $this->route('grade')) {
            return Gate::allows('publish', $grade);
        }

        return Gate::allows('encodeGrades', $this->route('course_offering'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'period' => ['required', Rule::in(GradePublicationService::PERIODS)],
        ];
    }
}
