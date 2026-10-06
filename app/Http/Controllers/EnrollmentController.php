<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnrollmentRequest;
use App\Http\Requests\UpdateEnrollmentRequest;
use App\Http\Resources\EnrollmentResource;
use App\Models\Enrollment;
use App\Traits\Sortable;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    use Sortable;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 15);

        $query = Enrollment::query()
            ->when($request->query('search'), function ($query, $search) {
                $query->whereHas('student', function ($q) use ($search) {
                    $q->where('student_number', 'like', "%{$search}%");
                });
            })
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('student_id'), fn ($q, $student_id) => $q->where('student_id', $student_id))
            ->when($request->query('course_offering_id'), fn ($q, $offering_id) => $q->where('course_offering_id', $offering_id));

        $enrollments = $this->applySorting($query, $request)->paginate($perPage);

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
