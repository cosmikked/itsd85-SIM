<?php

namespace App\Models;

use Database\Factories\GradeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
    'midterm_status',
    'midterm_published_at',
    'final_status',
    'final_published_at',
])]
class Grade extends Model
{
    /** @use HasFactory<GradeFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Mirror the column defaults so a model that has not been refreshed
     * after create() still reports its real publication state.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'midterm_status' => 'draft',
        'final_status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'is_inc' => 'boolean',
            'inc_expiration_date' => 'date',
            'midterm_published_at' => 'datetime',
            'final_published_at' => 'datetime',
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

    public function isMidtermPublished(): bool
    {
        return $this->midterm_status === 'published';
    }

    public function isFinalPublished(): bool
    {
        return $this->final_status === 'published';
    }

    /**
     * A grade is visible to students and registrars once any period is published.
     */
    public function isAnyPublished(): bool
    {
        return $this->isMidtermPublished() || $this->isFinalPublished();
    }

    /**
     * @param  Builder<Grade>  $query
     */
    public function scopeAnyPublished(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->where('midterm_status', 'published')->orWhere('final_status', 'published'));
    }
}
