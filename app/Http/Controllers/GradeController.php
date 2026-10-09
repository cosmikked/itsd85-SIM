<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublishGradeRequest;
use App\Http\Requests\StoreGradeRequest;
use App\Http\Requests\UpdateGradeRequest;
use App\Http\Resources\GradeResource;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Services\GradeCalculatorService;
use App\Services\GradePublicationService;
use App\Traits\Sortable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class GradeController extends Controller
{
    use Sortable;

    public function __construct(
        private GradeCalculatorService $gradeService,
        private GradePublicationService $publication,
    ) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Grade::class);

        $perPage = $request->query('per_page', 15);

        $query = Grade::query()
            // drafts are private to the instructor and administrators
            ->when($request->user()->role === 'registrar', fn ($q) => $q->anyPublished())
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

        $this->publication->assertWithinDeadlines($enrollment->courseOffering->academicTerm, $data, $request->user());

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
        Gate::authorize('view', $grade);

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

        $this->publication->assertEditable($grade, $data, $request->user());
        $this->publication->assertWithinDeadlines($grade->enrollment->courseOffering->academicTerm, $data, $request->user(), $grade);

        $grade = $this->gradeService->computeGrade($grade, $data);
        $grade->save();

        return GradeResource::make($grade)->additional([
            'success' => true,
            'message' => 'Grade updated successfully.',
        ]);
    }

    /**
     * Publish the midterm or final result of one grade so the student can see it.
     */
    public function publish(PublishGradeRequest $request, Grade $grade)
    {
        $grade = $this->publication->publish($grade, $request->validated('period'), $request->user());

        return GradeResource::make($grade)->additional([
            'success' => true,
            'message' => 'Grade published successfully.',
        ]);
    }

    public function destroy(Grade $grade): Response
    {
        $grade->delete();

        return response()->noContent();
    }
}
