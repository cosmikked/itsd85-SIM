<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use Illuminate\Http\Response;

class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $students = Student::paginate(15);

        return StudentResource::collection($students)->additional([
            'success' => true,
            'message' => 'Students retrieved successfully.',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStudentRequest $request)
    {
        $student = Student::create($request->validated())->refresh();

        return StudentResource::make($student)->additional([
            'success' => true,
            'message' => 'Student created successfully.',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Student $student)
    {
        return StudentResource::make($student)->additional([
            'success' => true,
            'message' => 'Student retrieved successfully.',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStudentRequest $request, Student $student)
    {
        $student->update($request->validated());

        return StudentResource::make($student)->additional([
            'success' => true,
            'message' => 'Student updated successfully.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Student $student): Response
    {
        if ($student->enrollments()->exists()) {
            abort(409, 'Student cannot be deleted because it still has enrollments.');
        }

        $student->delete();

        return response()->noContent();
    }
}
