<?php

namespace App\Models;

use Database\Factories\GradeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'enrollment_id',
    'midterm_raw_score',
    'midterm_equivalent_grade',
    'finalterm_raw_score',
    'finalterm_equivalent_grade',
    'final_raw_score',
    'final_equivalent_grade',
    're_exam_raw_score',
    're_exam_equivalent_grade',
    'remarks',
    'is_inc',
    'inc_expiration_date',
])]
class Grade extends Model
{
    /** @use HasFactory<GradeFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_inc' => 'boolean',
            'inc_expiration_date' => 'date',
            'midterm_raw_score' => 'float',
            'midterm_equivalent_grade' => 'float',
            'finalterm_raw_score' => 'float',
            'finalterm_equivalent_grade' => 'float',
            'final_raw_score' => 'float',
            'final_equivalent_grade' => 'float',
            're_exam_raw_score' => 'float',
            're_exam_equivalent_grade' => 'float',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
