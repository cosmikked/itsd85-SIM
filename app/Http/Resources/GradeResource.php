<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class GradeResource extends JsonResource
{
    private const MIDTERM_FIELDS = [
        'midterm_raw_score',
        'midterm_equivalent_grade',
    ];

    private const FINAL_FIELDS = [
        'finalterm_raw_score',
        'finalterm_equivalent_grade',
        'final_raw_score',
        'final_equivalent_grade',
        're_exam_raw_score',
        're_exam_equivalent_grade',
        'remarks',
        'is_inc',
        'inc_expiration_date',
    ];

    /**
     * Administrators and the instructor see everything, drafts included.
     * Everyone else (students, registrars) only gets the published periods.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        if (in_array($request->user()?->role, ['administrator', 'instructor'], true)) {
            return $data;
        }

        $hidden = [];

        if (! $this->isMidtermPublished()) {
            $hidden = array_merge($hidden, self::MIDTERM_FIELDS);
        }

        if (! $this->isFinalPublished()) {
            $hidden = array_merge($hidden, self::FINAL_FIELDS);
        }

        return Arr::except($data, $hidden);
    }
}
