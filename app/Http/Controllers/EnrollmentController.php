<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnrollmentRequest;
use App\Http\Requests\UpdateEnrollmentRequest;
use App\Http\Resources\EnrollmentResource;
use App\Models\Enrollment;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 15);
        $enrollments = Enrollment::paginate($perPage);

        return EnrollmentResource::collection($enrollments)->additional([
            'success' => true,
            'message' => 'Enrollments retrieved successfully.',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEnrollmentRequest $request)
    {
        $enrollment = Enrollment::create($request->validated())->refresh();

        return EnrollmentResource::make($enrollment)->additional([
            'success' => true,
            'message' => 'Enrollment created successfully.',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Enrollment $enrollment)
    {
        return EnrollmentResource::make($enrollment)->additional([
            'success' => true,
            'message' => 'Enrollment retrieved successfully.',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEnrollmentRequest $request, Enrollment $enrollment)
    {
        $enrollment->update($request->validated());

        return EnrollmentResource::make($enrollment)->additional([
            'success' => true,
            'message' => 'Enrollment updated successfully.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Enrollment $enrollment)
    {
        if ($enrollment->status === 'completed') {
            abort(409, 'Cannot delete a completed enrollment.');
        }

        $enrollment->delete();

        return response()->noContent();
    }
}
