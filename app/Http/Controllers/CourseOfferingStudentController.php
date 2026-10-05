<?php

namespace App\Http\Controllers;

use App\Http\Resources\StudentResource;
use App\Models\CourseOffering;

class CourseOfferingStudentController extends Controller
{
    public function index(CourseOffering $courseOffering)
    {
        $students = $courseOffering->enrollments()->with('student')->paginate(15)->pluck('student');

        // Wrap in paginator since pluck loses the pagination envelope if we just return it raw.
        // Or better, just paginate the students relationship directly:
        $paginatedStudents = $courseOffering->enrollments()->with('student')->paginate(15);
        $paginatedStudents->setCollection($paginatedStudents->pluck('student'));

        return StudentResource::collection($paginatedStudents)->additional([
            'success' => true,
            'message' => 'Course offering students retrieved successfully.',
        ]);
    }
}
