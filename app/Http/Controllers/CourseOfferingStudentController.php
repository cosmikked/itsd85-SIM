<?php

namespace App\Http\Controllers;

use App\Http\Resources\StudentResource;
use App\Models\CourseOffering;
use Illuminate\Support\Facades\Gate;

class CourseOfferingStudentController extends Controller
{
    public function index(CourseOffering $courseOffering)
    {
        Gate::authorize('viewRoster', $courseOffering);

        // pluck() on the paginator's collection would drop the pagination envelope,
        // so swap the collection on the paginator instead.
        $paginatedStudents = $courseOffering->enrollments()->with('student')->paginate(15);
        $paginatedStudents->setCollection($paginatedStudents->pluck('student'));

        return StudentResource::collection($paginatedStudents)->additional([
            'success' => true,
            'message' => 'Course offering students retrieved successfully.',
        ]);
    }
}
