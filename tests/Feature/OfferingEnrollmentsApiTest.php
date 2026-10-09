<?php

namespace Tests\Feature;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfferingEnrollmentsApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: CourseOffering}
     */
    private function instructorWithOffering(): array
    {
        $instructor = User::factory()->instructor()->create();
        $offering = CourseOffering::factory()->create(['instructor_id' => $instructor->id]);

        return [$instructor, $offering];
    }

    private function url(CourseOffering $offering): string
    {
        return "/api/v1/course-offerings/{$offering->id}/enrollments";
    }

    public function test_requires_authentication(): void
    {
        [, $offering] = $this->instructorWithOffering();

        $this->getJson($this->url($offering))->assertUnauthorized();
    }

    public function test_instructor_sees_enrollments_with_student_summary_and_draft_grade(): void
    {
        [$instructor, $offering] = $this->instructorWithOffering();
        $student = Student::factory()->create(['first_name' => 'Grace', 'last_name' => 'Hopper']);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'status' => 'enrolled',
        ]);
        Grade::factory()->create(['enrollment_id' => $enrollment->id, 'midterm_raw_score' => 88]);
        Sanctum::actingAs($instructor);

        $response = $this->getJson($this->url($offering));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Course offering enrollments retrieved successfully.')
            ->assertJsonPath('data.0.id', $enrollment->id)
            ->assertJsonPath('data.0.status', 'enrolled')
            ->assertJsonPath('data.0.student.first_name', 'Grace')
            ->assertJsonPath('data.0.student.last_name', 'Hopper')
            ->assertJsonPath('data.0.grade.midterm_raw_score', 88)
            ->assertJsonPath('data.0.grade.midterm_status', 'draft');
        // identification only: no contact details, birth date or address
        $this->assertSame(
            ['id', 'student_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'program_id', 'year_level'],
            array_keys($response->json('data.0.student')),
        );
    }

    public function test_enrollment_without_a_grade_has_a_null_grade(): void
    {
        [$instructor, $offering] = $this->instructorWithOffering();
        Enrollment::factory()->create(['course_offering_id' => $offering->id]);
        Sanctum::actingAs($instructor);

        $this->getJson($this->url($offering))->assertOk()->assertJsonPath('data.0.grade', null);
    }

    public function test_another_instructor_and_students_are_forbidden(): void
    {
        [, $offering] = $this->instructorWithOffering();
        $enrollment = Enrollment::factory()->create(['course_offering_id' => $offering->id]);

        foreach ([User::factory()->instructor()->create(), User::find($enrollment->student->user_id)] as $user) {
            Sanctum::actingAs($user);

            $this->getJson($this->url($offering))->assertForbidden();
        }
    }

    public function test_administrator_sees_drafts_and_registrar_only_sees_published_grades(): void
    {
        [, $offering] = $this->instructorWithOffering();
        $draft = Enrollment::factory()->create(['course_offering_id' => $offering->id]);
        $published = Enrollment::factory()->create(['course_offering_id' => $offering->id]);
        $midtermOnly = Enrollment::factory()->create(['course_offering_id' => $offering->id]);
        Grade::factory()->create(['enrollment_id' => $draft->id, 'midterm_raw_score' => 70]);
        Grade::factory()->published()->create(['enrollment_id' => $published->id, 'midterm_raw_score' => 80, 'remarks' => 'Passed']);
        Grade::factory()->midtermPublished()->create(['enrollment_id' => $midtermOnly->id, 'midterm_raw_score' => 90, 'remarks' => 'Passed']);

        Sanctum::actingAs(User::factory()->administrator()->create());
        $admin = collect($this->getJson($this->url($offering))->assertOk()->json('data'))->keyBy('id');
        $this->assertSame(70, $admin[$draft->id]['grade']['midterm_raw_score']);

        Sanctum::actingAs(User::factory()->registrar()->create());
        $registrar = collect($this->getJson($this->url($offering))->assertOk()->json('data'))->keyBy('id');
        $this->assertNull($registrar[$draft->id]['grade']);
        $this->assertSame(80, $registrar[$published->id]['grade']['midterm_raw_score']);
        $this->assertSame('Passed', $registrar[$published->id]['grade']['remarks']);
        $this->assertSame(90, $registrar[$midtermOnly->id]['grade']['midterm_raw_score']);
        $this->assertArrayNotHasKey('remarks', $registrar[$midtermOnly->id]['grade']);
    }

    public function test_dropped_enrollments_are_included_and_can_be_filtered(): void
    {
        [$instructor, $offering] = $this->instructorWithOffering();
        Enrollment::factory()->create(['course_offering_id' => $offering->id, 'status' => 'enrolled']);
        Enrollment::factory()->create(['course_offering_id' => $offering->id, 'status' => 'dropped']);
        Sanctum::actingAs($instructor);

        $this->getJson($this->url($offering))->assertOk()->assertJsonCount(2, 'data');
        $this->getJson($this->url($offering).'?status=dropped')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'dropped');
    }

    public function test_enrollments_can_be_searched_by_student_number_and_name(): void
    {
        [$instructor, $offering] = $this->instructorWithOffering();
        $a = Student::factory()->create(['student_number' => '2026-11111', 'last_name' => 'Lovelace']);
        $b = Student::factory()->create(['student_number' => '2026-22222', 'last_name' => 'Turing']);
        Enrollment::factory()->create(['student_id' => $a->id, 'course_offering_id' => $offering->id]);
        Enrollment::factory()->create(['student_id' => $b->id, 'course_offering_id' => $offering->id]);
        Sanctum::actingAs($instructor);

        $this->getJson($this->url($offering).'?search=11111')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student.last_name', 'Lovelace');
        $this->getJson($this->url($offering).'?search=Turing')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student_id', $b->id);
    }

    public function test_results_are_paginated_and_do_not_n_plus_one(): void
    {
        [$instructor, $offering] = $this->instructorWithOffering();
        $program = Program::factory()->create();
        Enrollment::factory()->count(20)->recycle($program)->create(['course_offering_id' => $offering->id])
            ->each(fn ($e) => Grade::factory()->create(['enrollment_id' => $e->id]));
        Sanctum::actingAs($instructor);

        DB::enableQueryLog();
        $this->getJson($this->url($offering).'?per_page=20')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 20);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // auth + offering + policy + count + enrollments + students + grades; never one per row
        $this->assertLessThan(15, $queries, "expected a flat query count, got {$queries}");
    }
}
