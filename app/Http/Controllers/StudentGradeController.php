<?php

namespace App\Http\Controllers;

use App\Http\Resources\GradeResource;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Support\Facades\Gate;

class StudentGradeController extends Controller
{
    public function index(Student $student)
    {
        Gate::authorize('view', $student);

        $grades = Grade::whereHas('enrollment', function ($q) use ($student) {
            $q->where('student_id', $student->id);
        })
            // drafts are private to the instructor and administrators
            ->when(request()->user()->role !== 'administrator', fn ($q) => $q->anyPublished())
            ->paginate(15);

        return GradeResource::collection($grades)->additional([
            'success' => true,
            'message' => 'Student grades retrieved successfully.',
        ]);
    }
}
