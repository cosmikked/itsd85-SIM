<?php

namespace App\Http\Controllers;

use App\Http\Resources\EnrollmentResource;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Traits\Sortable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CourseOfferingEnrollmentController extends Controller
{
    use Sortable;

    /**
     * The grading-sheet roster: every enrollment of the offering with a student
     * summary and the grade. Drafts are only visible to the instructor and administrators.
     */
    public function index(Request $request, CourseOffering $courseOffering)
    {
        Gate::authorize('viewRoster', $courseOffering);

        $perPage = $request->query('per_page', 15);

        $query = Enrollment::query()
            ->where('course_offering_id', $courseOffering->id)
            ->with(['student', 'grade'])
            ->when($request->query('search'), function ($query, $search) {
                $query->whereHas('student', function ($q) use ($search) {
                    $q->where('student_number', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status));

        $enrollments = $this->applySorting($query, $request)->paginate($perPage);

        return EnrollmentResource::collection($enrollments)->additional([
            'success' => true,
            'message' => 'Course offering enrollments retrieved successfully.',
        ]);
    }
}
