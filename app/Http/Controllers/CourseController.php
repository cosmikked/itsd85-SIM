<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Traits\Sortable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CourseController extends Controller
{
    use Sortable;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 15);

        $query = Course::query()
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('course_title', 'like', "%{$search}%")
                        ->orWhere('course_code', 'like', "%{$search}%");
                });
            })
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status));

        $courses = $this->applySorting($query, $request)->paginate($perPage);

        return CourseResource::collection($courses)->additional([
            'success' => true,
            'message' => 'Courses retrieved successfully.',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCourseRequest $request)
    {
        $course = Course::create($request->validated())->refresh();

        return CourseResource::make($course)->additional([
            'success' => true,
            'message' => 'Course created successfully.',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Course $course)
    {
        return CourseResource::make($course)->additional([
            'success' => true,
            'message' => 'Course retrieved successfully.',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCourseRequest $request, Course $course)
    {
        $course->update($request->validated());

        return CourseResource::make($course)->additional([
            'success' => true,
            'message' => 'Course updated successfully.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Course $course): Response
    {
        if ($course->courseOfferings()->exists()) {
            abort(409, 'Course cannot be deleted because it still has course offerings.');
        }

        $course->delete();

        return response()->noContent();
    }
}
