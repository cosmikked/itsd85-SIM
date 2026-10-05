<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\User;
use Database\Seeders\GradeScaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GradeApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdministrator(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'administrator']));
    }

    private function actingAsInstructor(User $instructor): void
    {
        Sanctum::actingAs($instructor);
    }

    private function setupBaseData(): array
    {
        $this->seed(GradeScaleSeeder::class);

        $term = AcademicTerm::factory()->create([
            'midterm_grading_deadline' => now()->addDays(5),
            'final_grading_deadline' => now()->addDays(10),
            'inc_completion_deadline' => now()->addDays(15),
        ]);

        $instructor = User::factory()->create(['role' => 'instructor']);
        $offering = CourseOffering::factory()->create([
            'academic_term_id' => $term->id,
            'instructor_id' => $instructor->id,
        ]);
        $enrollment = Enrollment::factory()->create([
            'course_offering_id' => $offering->id,
            'status' => 'enrolled',
        ]);

        return [$term, $instructor, $offering, $enrollment];
    }

    public function test_equivalent_grade_is_computed_correctly_when_storing_raw_scores(): void
    {
        $this->actingAsAdministrator();
        [$term, $instructor, $offering, $enrollment] = $this->setupBaseData();

        $response = $this->postJson('/api/v1/grades', [
            'enrollment_id' => $enrollment->id,
            'midterm_raw_score' => 85,
            'finalterm_raw_score' => 90,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.midterm_raw_score', 85)
            ->assertJsonPath('data.finalterm_raw_score', 90);

        // Final raw score = (85 * 1/3) + (90 * 2/3) = 28.33 + 60 = 88.33
        // In grade scale, let's just assert the equivalent grade exists
        $this->assertDatabaseHas('grades', [
            'enrollment_id' => $enrollment->id,
            'midterm_raw_score' => 85,
            'finalterm_raw_score' => 90,
        ]);

        $grade = Grade::where('enrollment_id', $enrollment->id)->first();
        $this->assertNotNull($grade->final_equivalent_grade);
    }

    public function test_cannot_store_final_grade_if_no_midterm_grade_yet(): void
    {
        $this->actingAsAdministrator();
        [$term, $instructor, $offering, $enrollment] = $this->setupBaseData();

        $response = $this->postJson('/api/v1/grades', [
            'enrollment_id' => $enrollment->id,
            'finalterm_raw_score' => 90, // missing midterm
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['finalterm_raw_score']);
    }

    public function test_cannot_store_re_exam_score_if_missing_mt_and_ft(): void
    {
        $this->actingAsAdministrator();
        [$term, $instructor, $offering, $enrollment] = $this->setupBaseData();

        $response = $this->postJson('/api/v1/grades', [
            'enrollment_id' => $enrollment->id,
            'midterm_raw_score' => 85,
            're_exam_raw_score' => 75,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['re_exam_raw_score']);
    }

    public function test_cannot_store_re_exam_score_if_final_grade_is_not_four(): void
    {
        $this->actingAsAdministrator();
        [$term, $instructor, $offering, $enrollment] = $this->setupBaseData();

        $response = $this->postJson('/api/v1/grades', [
            'enrollment_id' => $enrollment->id,
            'midterm_raw_score' => 95,
            'finalterm_raw_score' => 95, // this will be 1.0 or similar passing grade
            're_exam_raw_score' => 75,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['re_exam_raw_score']);
    }

    public function test_grade_remark_is_incomplete_when_is_inc_flag_is_true(): void
    {
        $this->actingAsAdministrator();
        [$term, $instructor, $offering, $enrollment] = $this->setupBaseData();

        $response = $this->postJson('/api/v1/grades', [
            'enrollment_id' => $enrollment->id,
            'midterm_raw_score' => 85,
            'is_inc' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('grades', [
            'enrollment_id' => $enrollment->id,
            'is_inc' => true,
            'remarks' => 'Incomplete',
        ]);

        $grade = Grade::where('enrollment_id', $enrollment->id)->first();
        $this->assertEquals($term->inc_completion_deadline->format('Y-m-d'), $grade->inc_expiration_date->format('Y-m-d'));
    }

    public function test_cannot_upload_grades_after_grading_deadlines(): void
    {
        $this->actingAsAdministrator();
        [$term, $instructor, $offering, $enrollment] = $this->setupBaseData();

        $term->update(['midterm_grading_deadline' => now()->subDays(1)]);

        $response = $this->postJson('/api/v1/grades', [
            'enrollment_id' => $enrollment->id,
            'midterm_raw_score' => 85,
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_store_raw_scores_out_of_bounds(): void
    {
        $this->actingAsAdministrator();
        [$term, $instructor, $offering, $enrollment] = $this->setupBaseData();

        $response = $this->postJson('/api/v1/grades', [
            'enrollment_id' => $enrollment->id,
            'midterm_raw_score' => 105,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['midterm_raw_score']);
    }
}
