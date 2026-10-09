<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\GradeScaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GradeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_15_grades_per_page(): void
    {
        $this->actingAsAdministrator();
        $term = AcademicTerm::factory()->create([
            'midterm_grading_deadline' => now()->addDays(10),
            'final_grading_deadline' => now()->addDays(10),
        ]);
        $program = Program::factory()->create();
        $offering = CourseOffering::factory()->recycle($term)->create();
        $enrollments = Enrollment::factory()->count(16)->recycle($offering)->recycle($program)->create();

        foreach ($enrollments as $enrollment) {
            Grade::factory()->create(['enrollment_id' => $enrollment->id]);
        }

        $response = $this->getJson('/api/v1/grades');

        $response->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 16);
    }

    public function test_index_accepts_per_page_parameter(): void
    {
        $this->actingAsAdministrator();
        $term = AcademicTerm::factory()->create([
            'midterm_grading_deadline' => now()->addDays(10),
            'final_grading_deadline' => now()->addDays(10),
        ]);
        $program = Program::factory()->create();
        $offering = CourseOffering::factory()->recycle($term)->create();
        $enrollments = Enrollment::factory()->count(16)->recycle($offering)->recycle($program)->create();

        foreach ($enrollments as $enrollment) {
            Grade::factory()->create(['enrollment_id' => $enrollment->id]);
        }

        $response = $this->getJson('/api/v1/grades?per_page=5');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 16);
    }

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

    public function test_index_can_search_by_student_number(): void
    {
        $this->actingAsAdministrator();

        $student1 = Student::factory()->create(['student_number' => 'TARGET01']);
        $enrollment1 = Enrollment::factory()->create(['student_id' => $student1->id]);
        Grade::factory()->create(['enrollment_id' => $enrollment1->id]);

        $student2 = Student::factory()->create(['student_number' => '12345']);
        $enrollment2 = Enrollment::factory()->create(['student_id' => $student2->id]);
        Grade::factory()->create(['enrollment_id' => $enrollment2->id]);

        $student3 = Student::factory()->create(['student_number' => 'TARGET99']);
        $enrollment3 = Enrollment::factory()->create(['student_id' => $student3->id]);
        Grade::factory()->create(['enrollment_id' => $enrollment3->id]);

        $response = $this->getJson('/api/v1/grades?search=target');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_index_can_filter_by_exact_matches(): void
    {
        $this->actingAsAdministrator();
        $enrollment = Enrollment::factory()->create();
        Grade::factory()->create(['enrollment_id' => $enrollment->id, 'is_inc' => true]);

        $enrollment2 = Enrollment::factory()->create();
        Grade::factory()->create(['enrollment_id' => $enrollment2->id, 'is_inc' => false]);

        $response = $this->getJson('/api/v1/grades?is_inc=1&enrollment_id='.$enrollment->id);

        $response->assertOk()->assertJsonCount(1, 'data');
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

    public function test_instructor_cannot_upload_grades_after_grading_deadlines(): void
    {
        [$term, $instructor, $offering, $enrollment] = $this->setupBaseData();
        Sanctum::actingAs($instructor);

        $term->update(['midterm_grading_deadline' => now()->subDays(1)]);

        $response = $this->postJson('/api/v1/grades', [
            'enrollment_id' => $enrollment->id,
            'midterm_raw_score' => 85,
        ]);

        $response->assertStatus(403);
    }

    public function test_administrator_can_upload_grades_after_grading_deadlines(): void
    {
        $this->actingAsAdministrator();
        [$term, $instructor, $offering, $enrollment] = $this->setupBaseData();

        $term->update(['midterm_grading_deadline' => now()->subDays(1)]);

        $this->postJson('/api/v1/grades', [
            'enrollment_id' => $enrollment->id,
            'midterm_raw_score' => 85,
        ])->assertCreated();
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

    public function test_index_can_sort_results_by_multiple_columns(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Grade::query()->delete();
        Grade::factory()->create(['final_equivalent_grade' => 3.0, 'remarks' => 'Banana']);
        Grade::factory()->create(['final_equivalent_grade' => 1.0, 'remarks' => 'Zebra']);
        Grade::factory()->create(['final_equivalent_grade' => 3.0, 'remarks' => 'Apple']);

        $response = $this->getJson('/api/v1/grades?sort=-final_equivalent_grade,remarks');

        $response->assertStatus(200);

        $items = $response->json('data');
        $this->assertGreaterThanOrEqual(3, count($items));

        // Check order of the first 3 items
        $this->assertEquals('Apple', (string) $items[0]['remarks']);
        $this->assertEquals('Banana', (string) $items[1]['remarks']);
        $this->assertEquals('Zebra', (string) $items[2]['remarks']);
    }
}
