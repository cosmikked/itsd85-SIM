<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\GradeScaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GradePublishingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GradeScaleSeeder::class);
    }

    // ---- helpers --------------------------------------------------------

    /**
     * @param  array<string, mixed>  $termOverrides
     * @return array{0: User, 1: CourseOffering, 2: AcademicTerm}
     */
    private function instructorWithOffering(array $termOverrides = []): array
    {
        $instructor = User::factory()->instructor()->create();
        $term = AcademicTerm::factory()->create(array_merge([
            'midterm_grading_deadline' => now()->addDays(5),
            'final_grading_deadline' => now()->addDays(10),
            'inc_completion_deadline' => now()->addDays(30),
        ], $termOverrides));
        $offering = CourseOffering::factory()->create([
            'instructor_id' => $instructor->id,
            'academic_term_id' => $term->id,
        ]);

        return [$instructor, $offering, $term];
    }

    private function enrollIn(CourseOffering $offering): Enrollment
    {
        return Enrollment::factory()->create([
            'course_offering_id' => $offering->id,
            'status' => 'enrolled',
        ]);
    }

    private function actingAsStudentOf(Enrollment $enrollment): void
    {
        Sanctum::actingAs(User::find($enrollment->student->user_id));
    }

    private function actingAsAdministrator(): void
    {
        Sanctum::actingAs(User::factory()->administrator()->create());
    }

    private function publish(Grade $grade, string $period)
    {
        return $this->postJson("/api/v1/grades/{$grade->id}/publish", ['period' => $period]);
    }

    // ---- drafts ---------------------------------------------------------

    public function test_a_grade_created_by_the_instructor_starts_as_a_draft(): void
    {
        [$instructor, $offering] = $this->instructorWithOffering();
        $enrollment = $this->enrollIn($offering);
        Sanctum::actingAs($instructor);

        $this->postJson('/api/v1/grades', [
            'enrollment_id' => $enrollment->id,
            'midterm_raw_score' => 85,
        ])->assertCreated()
            ->assertJsonPath('data.midterm_status', 'draft')
            ->assertJsonPath('data.final_status', 'draft');
    }

    public function test_instructor_can_keep_editing_a_draft(): void
    {
        [$instructor, $offering] = $this->instructorWithOffering();
        $grade = Grade::factory()->create([
            'enrollment_id' => $this->enrollIn($offering)->id,
            'midterm_raw_score' => 70,
        ]);
        Sanctum::actingAs($instructor);

        $this->patchJson("/api/v1/grades/{$grade->id}", ['midterm_raw_score' => 80])
            ->assertOk()
            ->assertJsonPath('data.midterm_raw_score', 80);
        $this->patchJson("/api/v1/grades/{$grade->id}", ['midterm_raw_score' => 90, 'finalterm_raw_score' => 88])
            ->assertOk()
            ->assertJsonPath('data.midterm_raw_score', 90);
    }

    public function test_student_cannot_see_a_draft_grade(): void
    {
        [, $offering] = $this->instructorWithOffering();
        $enrollment = $this->enrollIn($offering);
        $grade = Grade::factory()->create(['enrollment_id' => $enrollment->id, 'midterm_raw_score' => 85]);
        $this->actingAsStudentOf($enrollment);

        $this->getJson("/api/v1/grades/{$grade->id}")->assertForbidden();
    }

    public function test_student_grade_list_excludes_drafts_and_hides_unpublished_fields(): void
    {
        [, $offering] = $this->instructorWithOffering();
        $student = Student::factory()->create();
        $draftEnrollment = Enrollment::factory()->create(['student_id' => $student->id, 'course_offering_id' => $offering->id]);
        Grade::factory()->create(['enrollment_id' => $draftEnrollment->id, 'midterm_raw_score' => 85]);

        [, $otherOffering] = $this->instructorWithOffering();
        $publishedEnrollment = Enrollment::factory()->create(['student_id' => $student->id, 'course_offering_id' => $otherOffering->id]);
        Grade::factory()->midtermPublished()->create(['enrollment_id' => $publishedEnrollment->id, 'midterm_raw_score' => 77]);

        Sanctum::actingAs(User::find($student->user_id));

        $response = $this->getJson("/api/v1/students/{$student->id}/grades");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.enrollment_id', $publishedEnrollment->id)
            ->assertJsonPath('data.0.midterm_raw_score', 77);
        $response->assertJsonMissingPath('data.0.remarks');
    }

    public function test_registrar_only_sees_published_grades(): void
    {
        [, $offering] = $this->instructorWithOffering();
        $draft = Grade::factory()->create(['enrollment_id' => $this->enrollIn($offering)->id, 'midterm_raw_score' => 85]);
        $published = Grade::factory()->published()->create(['enrollment_id' => $this->enrollIn($offering)->id]);
        Sanctum::actingAs(User::factory()->registrar()->create());

        $this->getJson("/api/v1/grades/{$draft->id}")->assertForbidden();
        $this->getJson("/api/v1/grades/{$published->id}")->assertOk();
        $this->getJson('/api/v1/grades')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id);
    }

    public function test_instructor_and_administrator_can_see_drafts(): void
    {
        [$instructor, $offering] = $this->instructorWithOffering();
        $grade = Grade::factory()->create(['enrollment_id' => $this->enrollIn($offering)->id, 'midterm_raw_score' => 85]);

        Sanctum::actingAs($instructor);
        $this->getJson("/api/v1/grades/{$grade->id}")->assertOk()->assertJsonPath('data.midterm_raw_score', 85);

        $this->actingAsAdministrator();
        $this->getJson("/api/v1/grades/{$grade->id}")->assertOk()->assertJsonPath('data.midterm_raw_score', 85);
    }

    public function test_academic_record_shows_ongoing_until_the_final_is_published(): void
    {
        [, $offering] = $this->instructorWithOffering();
        $enrollment = $this->enrollIn($offering);
        $grade = Grade::factory()->midtermPublished()->create([
            'enrollment_id' => $enrollment->id,
            'midterm_raw_score' => 85,
            'final_equivalent_grade' => 1.5,
            'remarks' => 'Passed',
        ]);
        $this->actingAsStudentOf($enrollment);
        $url = "/api/v1/students/{$enrollment->student_id}/academic-record";

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.0.enrollments.0.remarks', 'Ongoing')
            ->assertJsonPath('data.0.enrollments.0.final_equivalent_grade', null);

        $grade->update(['final_status' => 'published', 'final_published_at' => now()]);

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.0.enrollments.0.remarks', 'Passed')
            ->assertJsonPath('data.0.enrollments.0.final_equivalent_grade', 1.5);
    }

    // ---- midterm publishing --------------------------------------------

    public function test_publishing_the_midterm_reveals_only_midterm_fields_to_the_student(): void
    {
        [$instructor, $offering] = $this->instructorWithOffering();
        $enrollment = $this->enrollIn($offering);
        $grade = Grade::factory()->create([
            'enrollment_id' => $enrollment->id,
            'midterm_raw_score' => 85,
            'finalterm_raw_score' => 90,
            'remarks' => 'Passed',
        ]);

        Sanctum::actingAs($instructor);
        $this->publish($grade, 'midterm')
            ->assertOk()
            ->assertJsonPath('data.midterm_status', 'published')
            ->assertJsonPath('data.final_status', 'draft');

        $this->actingAsStudentOf($enrollment);
        $response = $this->getJson("/api/v1/grades/{$grade->id}");

        $response->assertOk()->assertJsonPath('data.midterm_raw_score', 85);
        $response->assertJsonMissingPath('data.finalterm_raw_score');
        $response->assertJsonMissingPath('data.remarks');
    }

    public function test_instructor_cannot_change_a_published_midterm_but_can_resend_the_same_value(): void
    {
        [$instructor, $offering] = $this->instructorWithOffering();
        $grade = Grade::factory()->midtermPublished()->create([
            'enrollment_id' => $this->enrollIn($offering)->id,
            'midterm_raw_score' => 80,
        ]);
        Sanctum::actingAs($instructor);

        $this->patchJson("/api/v1/grades/{$grade->id}", ['midterm_raw_score' => 90])->assertForbidden();
        $this->assertSame(80.0, $grade->fresh()->midterm_raw_score);

        $this->patchJson("/api/v1/grades/{$grade->id}", ['midterm_raw_score' => 80, 'finalterm_raw_score' => 85])
            ->assertOk()
            ->assertJsonPath('data.finalterm_raw_score', 85);
    }

    // ---- final publishing ----------------------------------------------

    public function test_publishing_the_final_reveals_final_results_and_remarks(): void
    {
        [$instructor, $offering] = $this->instructorWithOffering();
        $enrollment = $this->enrollIn($offering);
        Sanctum::actingAs($instructor);
        $created = $this->postJson('/api/v1/grades', [
            'enrollment_id' => $enrollment->id,
            'midterm_raw_score' => 85,
            'finalterm_raw_score' => 90,
        ])->assertCreated();
        $grade = Grade::find($created->json('data.id'));

        $this->publish($grade, 'midterm')->assertOk();
        $this->publish($grade, 'final')->assertOk()->assertJsonPath('data.final_status', 'published');

        $this->actingAsStudentOf($enrollment);
        $this->getJson("/api/v1/grades/{$grade->id}")
            ->assertOk()
            ->assertJsonPath('data.remarks', 'Passed')
            ->assertJsonPath('data.finalterm_raw_score', 90);
    }

    public function test_instructor_cannot_change_a_published_final_but_administrator_can_even_after_the_deadline(): void
    {
        [$instructor, $offering, $term] = $this->instructorWithOffering();
        $grade = Grade::factory()->published()->create([
            'enrollment_id' => $this->enrollIn($offering)->id,
            'midterm_raw_score' => 80,
            'finalterm_raw_score' => 80,
        ]);

        Sanctum::actingAs($instructor);
        $this->patchJson("/api/v1/grades/{$grade->id}", ['finalterm_raw_score' => 95])->assertForbidden();

        $term->update([
            'midterm_grading_deadline' => now()->subDays(2),
            'final_grading_deadline' => now()->subDay(),
        ]);

        $this->actingAsAdministrator();
        $this->patchJson("/api/v1/grades/{$grade->id}", ['finalterm_raw_score' => 95])
            ->assertOk()
            ->assertJsonPath('data.finalterm_raw_score', 95);
    }

    // ---- post-publication exceptions -----------------------------------

    public function test_instructor_can_enter_the_re_exam_score_once_on_a_published_conditional_grade(): void
    {
        [$instructor, $offering] = $this->instructorWithOffering();
        Sanctum::actingAs($instructor);
        $created = $this->postJson('/api/v1/grades', [
            'enrollment_id' => $this->enrollIn($offering)->id,
            'midterm_raw_score' => 40,
            'finalterm_raw_score' => 40,
        ])->assertCreated();
        $grade = Grade::find($created->json('data.id'));
        $this->publish($grade, 'midterm')->assertOk();
        $this->publish($grade, 'final')->assertOk();

        $this->patchJson("/api/v1/grades/{$grade->id}", ['re_exam_raw_score' => 75])
            ->assertOk()
            ->assertJsonPath('data.re_exam_equivalent_grade', 3)
            ->assertJsonPath('data.remarks', 'Passed');

        $this->patchJson("/api/v1/grades/{$grade->id}", ['re_exam_raw_score' => 20])->assertForbidden();
    }

    public function test_instructor_can_complete_a_published_inc_grade(): void
    {
        [$instructor, $offering] = $this->instructorWithOffering();
        $grade = Grade::factory()->published()->create([
            'enrollment_id' => $this->enrollIn($offering)->id,
            'midterm_raw_score' => 85,
            'is_inc' => true,
            'inc_expiration_date' => now()->addDays(30),
            'remarks' => 'Incomplete',
        ]);
        Sanctum::actingAs($instructor);

        $this->patchJson("/api/v1/grades/{$grade->id}", ['finalterm_raw_score' => 90, 'is_inc' => false])
            ->assertOk()
            ->assertJsonPath('data.is_inc', false)
            ->assertJsonPath('data.remarks', 'Passed');

        // completed -> locked again
        $this->patchJson("/api/v1/grades/{$grade->id}", ['finalterm_raw_score' => 50])->assertForbidden();
    }

    // ---- bulk save and publish -----------------------------------------

    public function test_bulk_save_applies_the_lock_per_row_and_rolls_everything_back(): void
    {
        [$instructor, $offering] = $this->instructorWithOffering();
        $locked = $this->enrollIn($offering);
        $open = $this->enrollIn($offering);
        Grade::factory()->midtermPublished()->create(['enrollment_id' => $locked->id, 'midterm_raw_score' => 80]);
        Sanctum::actingAs($instructor);

        $this->putJson("/api/v1/course-offerings/{$offering->id}/grades", [
            'grades' => [
                ['enrollment_id' => $open->id, 'midterm_raw_score' => 70],
                ['enrollment_id' => $locked->id, 'midterm_raw_score' => 90],
            ],
        ])->assertForbidden();

        $this->assertDatabaseMissing('grades', ['enrollment_id' => $open->id]);
        $this->assertSame(80.0, Grade::where('enrollment_id', $locked->id)->first()->midterm_raw_score);
    }

    public function test_offering_publish_releases_qualifying_grades_and_reports_the_rest_as_skipped(): void
    {
        [$instructor, $offering] = $this->instructorWithOffering();
        $ready = Grade::factory()->create(['enrollment_id' => $this->enrollIn($offering)->id, 'midterm_raw_score' => 85]);
        $this->enrollIn($offering); // no grade row at all
        $already = Grade::factory()->midtermPublished()->create(['enrollment_id' => $this->enrollIn($offering)->id, 'midterm_raw_score' => 70]);
        Sanctum::actingAs($instructor);
        $url = "/api/v1/course-offerings/{$offering->id}/grades/publish";

        $this->postJson($url, ['period' => 'midterm'])
            ->assertOk()
            ->assertJsonPath('data.period', 'midterm')
            ->assertJsonPath('data.published', 1)
            ->assertJsonPath('data.skipped', 2);

        $this->assertSame('published', $ready->fresh()->midterm_status);
        $this->assertNotNull($ready->fresh()->midterm_published_at);
        $this->assertSame('draft', $ready->fresh()->final_status);
        $this->assertSame('published', $already->fresh()->midterm_status);

        // idempotent
        $this->postJson($url, ['period' => 'midterm'])
            ->assertOk()
            ->assertJsonPath('data.published', 0)
            ->assertJsonPath('data.skipped', 3);
    }

    public function test_final_publish_requires_a_final_score_or_an_inc_flag(): void
    {
        [$instructor, $offering] = $this->instructorWithOffering();
        $onlyMidterm = Grade::factory()->create(['enrollment_id' => $this->enrollIn($offering)->id, 'midterm_raw_score' => 85]);
        $complete = Grade::factory()->create([
            'enrollment_id' => $this->enrollIn($offering)->id,
            'midterm_raw_score' => 85,
            'finalterm_raw_score' => 90,
        ]);
        $inc = Grade::factory()->create([
            'enrollment_id' => $this->enrollIn($offering)->id,
            'midterm_raw_score' => 85,
            'is_inc' => true,
        ]);
        Sanctum::actingAs($instructor);

        $this->postJson("/api/v1/course-offerings/{$offering->id}/grades/publish", ['period' => 'final'])
            ->assertOk()
            ->assertJsonPath('data.published', 2)
            ->assertJsonPath('data.skipped', 1);

        $this->assertSame('draft', $onlyMidterm->fresh()->final_status);
        $this->assertSame('published', $complete->fresh()->final_status);
        $this->assertSame('published', $inc->fresh()->final_status);
    }

    public function test_publish_validation(): void
    {
        [$instructor, $offering] = $this->instructorWithOffering();
        $noScore = Grade::factory()->create(['enrollment_id' => $this->enrollIn($offering)->id]);
        Sanctum::actingAs($instructor);

        $this->publish($noScore, 'midterm')->assertUnprocessable();
        $this->publish($noScore, 'semester')->assertUnprocessable()->assertJsonValidationErrors(['period']);
        $this->postJson("/api/v1/course-offerings/{$offering->id}/grades/publish", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period']);
    }

    // ---- authorization and deadlines -----------------------------------

    public function test_publish_endpoints_enforce_authorization(): void
    {
        [, $offering] = $this->instructorWithOffering();
        $enrollment = $this->enrollIn($offering);
        $grade = Grade::factory()->create(['enrollment_id' => $enrollment->id, 'midterm_raw_score' => 85]);
        $single = "/api/v1/grades/{$grade->id}/publish";
        $bulk = "/api/v1/course-offerings/{$offering->id}/grades/publish";

        $this->postJson($single, ['period' => 'midterm'])->assertUnauthorized();
        $this->postJson($bulk, ['period' => 'midterm'])->assertUnauthorized();

        $outsiders = [
            User::factory()->instructor()->create(),
            User::factory()->registrar()->create(),
            User::find($enrollment->student->user_id),
        ];

        foreach ($outsiders as $user) {
            Sanctum::actingAs($user);

            $this->postJson($single, ['period' => 'midterm'])->assertForbidden();
            $this->postJson($bulk, ['period' => 'midterm'])->assertForbidden();
        }

        $this->assertSame('draft', $grade->fresh()->midterm_status);
    }

    public function test_instructor_cannot_publish_after_the_deadline_but_administrator_can(): void
    {
        [$instructor, $offering] = $this->instructorWithOffering(['midterm_grading_deadline' => now()->subDay()]);
        $grade = Grade::factory()->create(['enrollment_id' => $this->enrollIn($offering)->id, 'midterm_raw_score' => 85]);

        Sanctum::actingAs($instructor);
        $this->publish($grade, 'midterm')->assertForbidden();
        $this->postJson("/api/v1/course-offerings/{$offering->id}/grades/publish", ['period' => 'midterm'])
            ->assertForbidden();
        $this->assertSame('draft', $grade->fresh()->midterm_status);

        $this->actingAsAdministrator();
        $this->publish($grade, 'midterm')->assertOk();
        $this->assertSame('published', $grade->fresh()->midterm_status);
    }

    // ---- system-written grades -----------------------------------------

    public function test_dropping_an_enrollment_publishes_the_withdrawn_grade(): void
    {
        [, $offering] = $this->instructorWithOffering();
        $enrollment = $this->enrollIn($offering);

        $enrollment->update(['status' => 'dropped']);

        $grade = Grade::where('enrollment_id', $enrollment->id)->firstOrFail();
        $this->assertSame('Withdrawn', $grade->remarks);
        $this->assertSame('published', $grade->midterm_status);
        $this->assertSame('published', $grade->final_status);
    }

    public function test_expiring_an_inc_grade_publishes_the_failing_result(): void
    {
        [, $offering] = $this->instructorWithOffering();
        $grade = Grade::factory()->midtermPublished()->create([
            'enrollment_id' => $this->enrollIn($offering)->id,
            'is_inc' => true,
            'inc_expiration_date' => now()->subDay(),
            'remarks' => 'Incomplete',
        ]);

        $this->artisan('app:expire-inc-grades')->assertSuccessful();

        $grade->refresh();
        $this->assertSame('Failed', $grade->remarks);
        $this->assertSame('published', $grade->final_status);
        $this->assertNotNull($grade->final_published_at);
    }
}
