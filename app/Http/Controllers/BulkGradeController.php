<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublishGradeRequest;
use App\Http\Requests\UpdateBulkGradeRequest;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Services\GradeCalculatorService;
use App\Services\GradePublicationService;
use Illuminate\Support\Facades\DB;

class BulkGradeController extends Controller
{
    public function __construct(
        private GradeCalculatorService $gradeService,
        private GradePublicationService $publication,
    ) {}

    /**
     * Save grades for a whole offering. Rows stay drafts until they are published.
     */
    public function update(UpdateBulkGradeRequest $request, CourseOffering $courseOffering)
    {
        $data = $request->validated();
        $actor = $request->user();
        $term = $courseOffering->academicTerm;

        DB::transaction(function () use ($data, $courseOffering, $term, $actor) {
            foreach ($data['grades'] as $gradeData) {
                // Ensure enrollment belongs to this course offering
                $enrollment = Enrollment::where('id', $gradeData['enrollment_id'])
                    ->where('course_offering_id', $courseOffering->id)
                    ->first();

                if (! $enrollment) {
                    abort(422, "Enrollment {$gradeData['enrollment_id']} is invalid for this course offering.");
                }

                if (! in_array($enrollment->status, ['enrolled', 'active'])) {
                    abort(422, "Cannot grade inactive enrollment {$gradeData['enrollment_id']}.");
                }

                $grade = Grade::firstOrNew(['enrollment_id' => $enrollment->id]);
                $grade->setRelation('enrollment', $enrollment);
                $enrollment->setRelation('courseOffering', $courseOffering);
                $courseOffering->setRelation('academicTerm', $term);

                $this->publication->assertEditable($grade, $gradeData, $actor);
                $this->publication->assertWithinDeadlines($term, $gradeData, $actor, $grade);

                $grade = $this->gradeService->computeGrade($grade, $gradeData);
                $grade->save();
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Grades bulk updated successfully.',
        ]);
    }

    /**
     * Publish every qualifying grade of the offering for one period (midterm or final).
     */
    public function publish(PublishGradeRequest $request, CourseOffering $courseOffering)
    {
        $result = $this->publication->publishOffering(
            $courseOffering,
            $request->validated('period'),
            $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Grades published successfully.',
            'data' => $result,
        ]);
    }
}
