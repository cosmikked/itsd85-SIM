<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourseOfferingRequest;
use App\Http\Requests\UpdateCourseOfferingRequest;
use App\Http\Resources\CourseOfferingResource;
use App\Models\CourseOffering;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CourseOfferingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 15);
        $offerings = CourseOffering::paginate($perPage);

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
