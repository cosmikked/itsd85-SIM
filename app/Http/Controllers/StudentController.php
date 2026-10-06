<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Models\User;
use App\Traits\Sortable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    use Sortable;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 15);

        $query = Student::query()
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('student_number', 'like', "%{$search}%");
                });
            })
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('program_id'), fn ($q, $program_id) => $q->where('program_id', $program_id))
            ->when($request->query('year_level'), fn ($q, $year_level) => $q->where('year_level', $year_level));

        $students = $this->applySorting($query, $request)->paginate($perPage);

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
        $student = DB::transaction(function () use ($request) {
            $validated = $request->validated();

            $user = User::create([
                'name' => $validated['first_name'].' '.$validated['last_name'],
                'email' => $validated['email'],
                'password' => Hash::make('password'),
                'role' => 'student',
            ]);

            $validated['user_id'] = $user->id;

            return Student::create($validated)->refresh();
        });

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
