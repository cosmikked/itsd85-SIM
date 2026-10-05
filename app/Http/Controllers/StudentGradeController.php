<?php

namespace App\Http\Controllers;

use App\Http\Resources\GradeResource;
use App\Models\Grade;
use App\Models\Student;

class StudentGradeController extends Controller
{
    public function index(Student $student)
    {
        $grades = Grade::whereHas('enrollment', function ($q) use ($student) {
            $q->where('student_id', $student->id);
        })->paginate(15);

        return GradeResource::collection($grades)->additional([
            'success' => true,
            'message' => 'Student grades retrieved successfully.',
        ]);
    }
}
