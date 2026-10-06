<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGradeRequest;
use App\Http\Requests\UpdateGradeRequest;
use App\Http\Resources\GradeResource;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Services\GradeCalculatorService;
use App\Traits\Sortable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GradeController extends Controller
{
    use Sortable;

    public function __construct(private GradeCalculatorService $gradeService) {}

    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 15);

        $query = Grade::query()
            ->when($request->query('search'), function ($query, $search) {
                $query->whereHas('enrollment.student', function ($q) use ($search) {
                    $q->where('student_number', 'like', "%{$search}%");
                });
            })
            ->when($request->has('is_inc'), fn ($q) => $q->where('is_inc', filter_var($request->query('is_inc'), FILTER_VALIDATE_BOOLEAN)))
            ->when($request->query('enrollment_id'), fn ($q, $enrollment_id) => $q->where('enrollment_id', $enrollment_id));

        $grades = $this->applySorting($query, $request)->paginate($perPage);

        return GradeResource::collection($grades)->additional([
            'success' => true,
            'message' => 'Grades retrieved successfully.',
        ]);
    }

    public function store(StoreGradeRequest $request)
    {
        $data = $request->validated();

        $enrollment = Enrollment::with('courseOffering.academicTerm')->findOrFail($data['enrollment_id']);

        if (! in_array($enrollment->status, ['enrolled', 'active'])) {
            abort(422, 'Cannot submit grades for inactive enrollments.');
        }

        $this->checkDeadlines($enrollment->courseOffering->academicTerm, $data);

        $grade = new Grade(['enrollment_id' => $enrollment->id]);
        // Set relation for service use
        $grade->setRelation('enrollment', $enrollment);

        $grade = $this->gradeService->computeGrade($grade, $data);
        $grade->save();

        return GradeResource::make($grade)->additional([
            'success' => true,
            'message' => 'Grade created successfully.',
        ]);
    }

    public function show(Grade $grade)
    {
        return GradeResource::make($grade)->additional([
            'success' => true,
            'message' => 'Grade retrieved successfully.',
        ]);
    }

    public function update(UpdateGradeRequest $request, Grade $grade)
    {
        $data = $request->validated();

        $grade->loadMissing('enrollment.courseOffering.academicTerm');

        if (! in_array($grade->enrollment->status, ['enrolled', 'active'])) {
            abort(422, 'Cannot update grades for inactive enrollments.');
        }

        $this->checkDeadlines($grade->enrollment->courseOffering->academicTerm, $data);

        $grade = $this->gradeService->computeGrade($grade, $data);
        $grade->save();

        return GradeResource::make($grade)->additional([
            'success' => true,
            'message' => 'Grade updated successfully.',
        ]);
    }

    public function destroy(Grade $grade): Response
    {
        $grade->delete();

        return response()->noContent();
    }

    private function checkDeadlines($term, array $data): void
    {
        $now = now();

        if (array_key_exists('midterm_raw_score', $data) && $term->midterm_grading_deadline && clone $now > clone $term->midterm_grading_deadline) {
            abort(403, 'Midterm grading deadline has passed.');
        }

        if (array_key_exists('finalterm_raw_score', $data) && $term->final_grading_deadline && clone $now > clone $term->final_grading_deadline) {
            abort(403, 'Final grading deadline has passed.');
        }
    }
}
