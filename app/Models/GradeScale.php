<?php

namespace App\Models;

use Database\Factories\GradeScaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['min_score', 'max_score', 'grade_point', 'remarks'])]
class GradeScale extends Model
{
    /** @use HasFactory<GradeScaleFactory> */
    use HasFactory;
}
