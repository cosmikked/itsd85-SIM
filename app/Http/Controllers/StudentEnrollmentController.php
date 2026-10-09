<?php

namespace App\Http\Controllers;

use App\Http\Resources\EnrollmentResource;
use App\Models\Student;
use Illuminate\Support\Facades\Gate;

class StudentEnrollmentController extends Controller
{
    public function index(Student $student)
    {
        Gate::authorize('view', $student);

        $enrollments = $student->enrollments()->paginate(15);

        return EnrollmentResource::collection($enrollments)->additional([
            'success' => true,
            'message' => 'Student enrollments retrieved successfully.',
        ]);
    }
}
