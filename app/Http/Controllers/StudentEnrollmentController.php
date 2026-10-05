<?php

namespace App\Http\Controllers;

use App\Http\Resources\EnrollmentResource;
use App\Models\Student;

class StudentEnrollmentController extends Controller
{
    public function index(Student $student)
    {
        $enrollments = $student->enrollments()->paginate(15);

        return EnrollmentResource::collection($enrollments)->additional([
            'success' => true,
            'message' => 'Student enrollments retrieved successfully.',
        ]);
    }
}
