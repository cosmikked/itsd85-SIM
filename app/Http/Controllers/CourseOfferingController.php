<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourseOfferingRequest;
use App\Http\Requests\UpdateCourseOfferingRequest;
use App\Http\Resources\CourseOfferingResource;
use App\Models\CourseOffering;
use App\Traits\Sortable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CourseOfferingController extends Controller
{
    use Sortable;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', CourseOffering::class);

        $perPage = $request->query('per_page', 15);

        $query = CourseOffering::query();

        if ($request->user()->role === 'instructor') {
            $query->where('instructor_id', $request->user()->id);
        }

        $query->when($request->query('search'), function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('course', function ($q2) use ($search) {
                    $q2->where('course_title', 'like', "%{$search}%")
                        ->orWhere('course_code', 'like', "%{$search}%");
                })->orWhereHas('instructor', function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%");
                });
            });
        })
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('course_id'), fn ($q, $course_id) => $q->where('course_id', $course_id))
            ->when($request->query('academic_term_id'), fn ($q, $term_id) => $q->where('academic_term_id', $term_id))
            ->when($request->query('instructor_id'), fn ($q, $instructor_id) => $q->where('instructor_id', $instructor_id));

        $offerings = $this->applySorting($query, $request)->paginate($perPage);

        return CourseOfferingResource::collection($offerings)->additional([
            'success' => true,
            'message' => 'Course Offerings retrieved successfully.',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCourseOfferingRequest $request)
    {
        $offering = CourseOffering::create($request->validated())->refresh();

        return CourseOfferingResource::make($offering)->additional([
            'success' => true,
            'message' => 'Course Offering created successfully.',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(CourseOffering $courseOffering)
    {
        Gate::authorize('view', $courseOffering);

        return CourseOfferingResource::make($courseOffering)->additional([
            'success' => true,
            'message' => 'Course Offering retrieved successfully.',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCourseOfferingRequest $request, CourseOffering $courseOffering)
    {
        $courseOffering->update($request->validated());

        return CourseOfferingResource::make($courseOffering)->additional([
            'success' => true,
            'message' => 'Course Offering updated successfully.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CourseOffering $courseOffering): Response
    {
        if ($courseOffering->enrollments()->exists()) {
            abort(409, 'Course Offering cannot be deleted because it still has enrollments.');
        }

        $courseOffering->delete();

        return response()->noContent();
    }
}
