<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Grade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_grade_is_withdrawn_if_dropped_before_midterms(): void
    {
        $term = AcademicTerm::factory()->create([
            'midterm_grading_deadline' => now()->addDays(5),
        ]);

        $offering = CourseOffering::factory()->create(['academic_term_id' => $term->id]);
        $enrollment = Enrollment::factory()->create([
            'course_offering_id' => $offering->id,
            'status' => 'enrolled',
        ]);

        Grade::factory()->create([
            'enrollment_id' => $enrollment->id,
            'midterm_raw_score' => 85,
            'midterm_equivalent_grade' => 2.0,
        ]);

        // Drop
        $enrollment->update(['status' => 'dropped']);

        $this->assertDatabaseHas('grades', [
            'enrollment_id' => $enrollment->id,
            'midterm_raw_score' => null,
            'midterm_equivalent_grade' => null,
            'remarks' => 'Withdrawn',
        ]);
    }

    public function test_grade_is_withdrawn_if_dropped_after_midterms_and_passing(): void
    {
        $term = AcademicTerm::factory()->create([
            'midterm_grading_deadline' => now()->subDays(1),
        ]);

        $offering = CourseOffering::factory()->create(['academic_term_id' => $term->id]);
        $enrollment = Enrollment::factory()->create([
            'course_offering_id' => $offering->id,
            'status' => 'enrolled',
        ]);

        Grade::factory()->create([
            'enrollment_id' => $enrollment->id,
            'midterm_raw_score' => 85,
            'midterm_equivalent_grade' => 2.0, // Passing
        ]);

        // Drop
        $enrollment->update(['status' => 'dropped']);

        $this->assertDatabaseHas('grades', [
            'enrollment_id' => $enrollment->id,
            'midterm_raw_score' => null,
            'remarks' => 'Withdrawn',
        ]);
    }

    public function test_grade_is_failed_if_dropped_after_midterms_and_failing(): void
    {
        $term = AcademicTerm::factory()->create([
            'midterm_grading_deadline' => now()->subDays(1),
        ]);

        $offering = CourseOffering::factory()->create(['academic_term_id' => $term->id]);
        $enrollment = Enrollment::factory()->create([
            'course_offering_id' => $offering->id,
            'status' => 'enrolled',
        ]);

        Grade::factory()->create([
            'enrollment_id' => $enrollment->id,
            'midterm_raw_score' => 45,
            'midterm_equivalent_grade' => 5.0, // Failing
        ]);

        // Drop
        $enrollment->update(['status' => 'dropped']);

        $this->assertDatabaseHas('grades', [
            'enrollment_id' => $enrollment->id,
            'midterm_raw_score' => null,
            'final_equivalent_grade' => 5.0,
            'remarks' => 'Failed',
        ]);
    }
}
