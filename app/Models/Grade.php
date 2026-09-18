<?php

namespace App\Models;

use Database\Factories\GradeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'enrollment_id',
    'midterm_raw_score',
    'midterm_grade_scale_id',
    'final_raw_score',
    'final_grade_scale_id',
    'status',
])]
class Grade extends Model
{
    /** @use HasFactory<GradeFactory> */
    use HasFactory;

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function midtermGradeScale(): BelongsTo
    {
        return $this->belongsTo(GradeScale::class);
    }

    public function finalGradeScale(): BelongsTo
    {
        return $this->belongsTo(GradeScale::class);
    }
}
