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

class AuthorizationApiTest extends TestCase
{
    use RefreshDatabase;

    // Student Role Tests

    public function test_student_cannot_access_public_catalogs(): void
    {
        $student = Student::factory()->create();
        Sanctum::actingAs(User::find($student->user_id));

        $this->getJson('/api/v1/programs')->assertForbidden();
        $this->getJson('/api/v1/courses')->assertForbidden();
        $this->getJson('/api/v1/course-offerings')->assertForbidden();
    }

    public function test_student_can_view_own_profile(): void
    {
        $student = Student::factory()->create();
        Sanctum::actingAs(User::find($student->user_id));

        $this->getJson("/api/v1/students/{$student->id}")->assertOk();
    }

    public function test_student_cannot_view_other_student_profile(): void
    {
        $student = Student::factory()->create();
        $otherStudent = Student::factory()->create();

        Sanctum::actingAs(User::find($student->user_id));

        $this->getJson("/api/v1/students/{$otherStudent->id}")->assertForbidden();
    }

    public function test_student_can_view_own_enrollment(): void
    {
        $student = Student::factory()->create();
        $enrollment = Enrollment::factory()->create(['student_id' => $student->id]);

        Sanctum::actingAs(User::find($student->user_id));

        $this->getJson("/api/v1/enrollments/{$enrollment->id}")->assertOk();
    }

    public function test_student_cannot_view_other_student_enrollment(): void
    {
        $student = Student::factory()->create();
        $otherStudent = Student::factory()->create();
        $enrollment = Enrollment::factory()->create(['student_id' => $otherStudent->id]);

        Sanctum::actingAs(User::find($student->user_id));

        $this->getJson("/api/v1/enrollments/{$enrollment->id}")->assertForbidden();
    }

    public function test_student_can_view_enrolled_course_offering(): void
    {
        $student = Student::factory()->create();
        $offering = CourseOffering::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
        ]);

        Sanctum::actingAs(User::find($student->user_id));

        $this->getJson("/api/v1/course-offerings/{$offering->id}")->assertOk();
    }

    public function test_student_cannot_view_unenrolled_course_offering(): void
    {
        $student = Student::factory()->create();
        $offering = CourseOffering::factory()->create();

        Sanctum::actingAs(User::find($student->user_id));

        $this->getJson("/api/v1/course-offerings/{$offering->id}")->assertForbidden();
    }

    public function test_student_can_view_own_grade(): void
    {
        $this->seed(GradeScaleSeeder::class);
        $student = Student::factory()->create();
        $enrollment = Enrollment::factory()->create(['student_id' => $student->id]);
        $grade = Grade::factory()->published()->create(['enrollment_id' => $enrollment->id]);

        Sanctum::actingAs(User::find($student->user_id));

        $this->getJson("/api/v1/grades/{$grade->id}")->assertOk();
    }

    public function test_student_cannot_view_other_student_grade(): void
    {
        $this->seed(GradeScaleSeeder::class);
        $student = Student::factory()->create();
        $otherStudent = Student::factory()->create();
        $enrollment = Enrollment::factory()->create(['student_id' => $otherStudent->id]);
        $grade = Grade::factory()->create(['enrollment_id' => $enrollment->id]);

        Sanctum::actingAs(User::find($student->user_id));

        $this->getJson("/api/v1/grades/{$grade->id}")->assertForbidden();
    }

    public function test_student_cannot_create_or_update_records(): void
    {
        $student = Student::factory()->create();
        Sanctum::actingAs(User::find($student->user_id));

        $program = Program::factory()->create();

        $studentPayload = [
            'student_number' => '2026-00002',
            'first_name' => 'Bob',
            'last_name' => 'Builder',
            'birth_date' => '2005-01-01',
            'email' => 'bob@example.com',
            'contact_number' => '09171234568',
            'program_id' => $program->id,
            'year_level' => 1,
        ];

        $this->postJson('/api/v1/students', $studentPayload)->assertForbidden();

        $this->putJson("/api/v1/students/{$student->id}", ['first_name' => 'Updated'])->assertForbidden();

        $offering = CourseOffering::factory()->create();

        $this->postJson('/api/v1/enrollments', [
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
        ])->assertForbidden();
    }

    // Instructor Role Tests

    public function test_instructor_sees_only_assigned_course_offerings(): void
    {
        $instructor = User::factory()->instructor()->create();
        $otherInstructor = User::factory()->instructor()->create();

        $myOffering = CourseOffering::factory()->create(['instructor_id' => $instructor->id]);
        $otherOffering = CourseOffering::factory()->create(['instructor_id' => $otherInstructor->id]);

        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/v1/course-offerings');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $myOffering->id);
    }

    public function test_instructor_can_encode_grades_for_assigned_offerings(): void
    {
        $this->seed(GradeScaleSeeder::class);
        $instructor = User::factory()->instructor()->create();
        $term = AcademicTerm::factory()->create([
            'midterm_grading_deadline' => now()->addDays(5),
            'final_grading_deadline' => now()->addDays(10),
        ]);
        $offering = CourseOffering::factory()->create([
            'instructor_id' => $instructor->id,
            'academic_term_id' => $term->id,
        ]);
        $enrollment = Enrollment::factory()->create(['course_offering_id' => $offering->id, 'status' => 'enrolled']);

        Sanctum::actingAs($instructor);

        $this->postJson('/api/v1/grades', [
            'enrollment_id' => $enrollment->id,
            'midterm_raw_score' => 85,
        ])->assertCreated();
    }

    public function test_instructor_cannot_encode_grades_for_unassigned_offerings(): void
    {
        $this->seed(GradeScaleSeeder::class);
        $instructor = User::factory()->instructor()->create();
        $otherInstructor = User::factory()->instructor()->create();

        $term = AcademicTerm::factory()->create([
            'midterm_grading_deadline' => now()->addDays(5),
            'final_grading_deadline' => now()->addDays(10),
        ]);

        $offering = CourseOffering::factory()->create([
            'instructor_id' => $otherInstructor->id,
            'academic_term_id' => $term->id,
        ]);
        $enrollment = Enrollment::factory()->create(['course_offering_id' => $offering->id, 'status' => 'enrolled']);

        Sanctum::actingAs($instructor);

        $this->postJson('/api/v1/grades', [
            'enrollment_id' => $enrollment->id,
            'midterm_raw_score' => 85,
        ])->assertForbidden();
    }

    public function test_instructor_cannot_manage_academic_resources(): void
    {
        $instructor = User::factory()->instructor()->create();
        Sanctum::actingAs($instructor);

        $this->postJson('/api/v1/programs', [
            'code' => 'BSCS',
            'name' => 'Computer Science',
        ])->assertForbidden();

        $this->postJson('/api/v1/courses', [
            'code' => 'CS101',
            'name' => 'Intro to CS',
            'credits' => 3,
        ])->assertForbidden();
    }

    // Registrar Role Tests

    public function test_registrar_can_manage_academic_resources(): void
    {
        $registrar = User::factory()->registrar()->create();
        Sanctum::actingAs($registrar);

        $program = Program::factory()->create();

        $studentPayload = [
            'student_number' => '2026-00001',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'birth_date' => '2005-01-01',
            'email' => 'ada@example.com',
            'contact_number' => '09171234567',
            'program_id' => $program->id,
            'year_level' => 1,
        ];

        $this->postJson('/api/v1/students', $studentPayload)->assertCreated();
    }

    public function test_registrar_can_view_grades(): void
    {
        $this->seed(GradeScaleSeeder::class);
        $registrar = User::factory()->registrar()->create();
        Sanctum::actingAs($registrar);

        $grade = Grade::factory()->published()->create();

        $this->getJson("/api/v1/grades/{$grade->id}")->assertOk();
    }

    public function test_registrar_cannot_encode_grades(): void
    {
        $registrar = User::factory()->registrar()->create();
        Sanctum::actingAs($registrar);

        $term = AcademicTerm::factory()->create([
            'midterm_grading_deadline' => now()->addDays(5),
            'final_grading_deadline' => now()->addDays(10),
        ]);
        $offering = CourseOffering::factory()->create(['academic_term_id' => $term->id]);
        $enrollment = Enrollment::factory()->create(['course_offering_id' => $offering->id, 'status' => 'enrolled']);

        $this->postJson('/api/v1/grades', [
            'enrollment_id' => $enrollment->id,
            'midterm_raw_score' => 85,
        ])->assertForbidden();
    }

    // Nested student routes (object-level authorization)

    /**
     * @return array<int, string>
     */
    private function nestedStudentPaths(Student $student): array
    {
        return [
            "/api/v1/students/{$student->id}/enrollments",
            "/api/v1/students/{$student->id}/grades",
            "/api/v1/students/{$student->id}/academic-record",
        ];
    }

    public function test_student_can_view_own_nested_records(): void
    {
        $student = Student::factory()->create();
        Sanctum::actingAs(User::find($student->user_id));

        foreach ($this->nestedStudentPaths($student) as $path) {
            $this->getJson($path)->assertOk();
        }
    }

    public function test_student_cannot_view_another_students_nested_records(): void
    {
        $student = Student::factory()->create();
        $otherStudent = Student::factory()->create();
        Sanctum::actingAs(User::find($student->user_id));

        foreach ($this->nestedStudentPaths($otherStudent) as $path) {
            $this->getJson($path)->assertForbidden();
        }
    }

    public function test_instructor_cannot_view_student_nested_records(): void
    {
        $student = Student::factory()->create();
        Sanctum::actingAs(User::factory()->instructor()->create());

        foreach ($this->nestedStudentPaths($student) as $path) {
            $this->getJson($path)->assertForbidden();
        }
    }

    public function test_registrar_can_view_any_students_nested_records(): void
    {
        $student = Student::factory()->create();
        Sanctum::actingAs(User::factory()->registrar()->create());

        foreach ($this->nestedStudentPaths($student) as $path) {
            $this->getJson($path)->assertOk();
        }
    }

    // Course offering roster

    public function test_instructor_can_view_roster_of_own_offering(): void
    {
        $instructor = User::factory()->instructor()->create();
        $offering = CourseOffering::factory()->create(['instructor_id' => $instructor->id]);
        Enrollment::factory()->create(['course_offering_id' => $offering->id]);
        Sanctum::actingAs($instructor);

        $this->getJson("/api/v1/course-offerings/{$offering->id}/students")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_instructor_cannot_view_roster_of_another_instructors_offering(): void
    {
        $instructor = User::factory()->instructor()->create();
        $offering = CourseOffering::factory()->create([
            'instructor_id' => User::factory()->instructor()->create()->id,
        ]);
        Sanctum::actingAs($instructor);

        $this->getJson("/api/v1/course-offerings/{$offering->id}/students")->assertForbidden();
    }

    public function test_student_cannot_view_a_roster_even_for_their_own_offering(): void
    {
        $enrollment = Enrollment::factory()->create();
        Sanctum::actingAs(User::find($enrollment->student->user_id));

        $this->getJson("/api/v1/course-offerings/{$enrollment->course_offering_id}/students")->assertForbidden();
    }

    public function test_registrar_can_view_any_roster(): void
    {
        $offering = CourseOffering::factory()->create();
        Sanctum::actingAs(User::factory()->registrar()->create());

        $this->getJson("/api/v1/course-offerings/{$offering->id}/students")->assertOk();
    }

    // Enrollment visibility for instructors

    public function test_instructor_can_view_enrollment_in_own_offering(): void
    {
        $instructor = User::factory()->instructor()->create();
        $offering = CourseOffering::factory()->create(['instructor_id' => $instructor->id]);
        $enrollment = Enrollment::factory()->create(['course_offering_id' => $offering->id]);
        Sanctum::actingAs($instructor);

        $this->getJson("/api/v1/enrollments/{$enrollment->id}")->assertOk();
    }

    public function test_instructor_cannot_view_enrollment_in_another_instructors_offering(): void
    {
        $instructor = User::factory()->instructor()->create();
        $offering = CourseOffering::factory()->create([
            'instructor_id' => User::factory()->instructor()->create()->id,
        ]);
        $enrollment = Enrollment::factory()->create(['course_offering_id' => $offering->id]);
        Sanctum::actingAs($instructor);

        $this->getJson("/api/v1/enrollments/{$enrollment->id}")->assertForbidden();
    }

    public function test_instructor_cannot_list_all_enrollments(): void
    {
        Sanctum::actingAs(User::factory()->instructor()->create());

        $this->getJson('/api/v1/enrollments')->assertForbidden();
    }

    // Bulk grading

    /**
     * @return array{0: CourseOffering, 1: array<string, mixed>}
     */
    private function offeringWithBulkPayload(User $instructor): array
    {
        $this->seed(GradeScaleSeeder::class);
        $term = AcademicTerm::factory()->create([
            'midterm_grading_deadline' => now()->addDays(5),
            'final_grading_deadline' => now()->addDays(10),
        ]);
        $offering = CourseOffering::factory()->create([
            'instructor_id' => $instructor->id,
            'academic_term_id' => $term->id,
        ]);
        $enrollment = Enrollment::factory()->create(['course_offering_id' => $offering->id, 'status' => 'enrolled']);

        return [$offering, [
            'grades' => [['enrollment_id' => $enrollment->id, 'midterm_raw_score' => 85]],
        ]];
    }

    public function test_bulk_grading_requires_authentication(): void
    {
        [$offering, $payload] = $this->offeringWithBulkPayload(User::factory()->instructor()->create());

        $this->putJson("/api/v1/course-offerings/{$offering->id}/grades", $payload)->assertUnauthorized();
    }

    public function test_instructor_can_bulk_grade_own_offering(): void
    {
        $instructor = User::factory()->instructor()->create();
        [$offering, $payload] = $this->offeringWithBulkPayload($instructor);
        Sanctum::actingAs($instructor);

        $this->putJson("/api/v1/course-offerings/{$offering->id}/grades", $payload)->assertOk();
    }

    public function test_instructor_cannot_bulk_grade_another_instructors_offering(): void
    {
        [$offering, $payload] = $this->offeringWithBulkPayload(User::factory()->instructor()->create());
        Sanctum::actingAs(User::factory()->instructor()->create());

        $this->putJson("/api/v1/course-offerings/{$offering->id}/grades", $payload)->assertForbidden();
    }

    public function test_registrar_cannot_bulk_grade(): void
    {
        [$offering, $payload] = $this->offeringWithBulkPayload(User::factory()->instructor()->create());
        Sanctum::actingAs(User::factory()->registrar()->create());

        $this->putJson("/api/v1/course-offerings/{$offering->id}/grades", $payload)->assertForbidden();
    }

    public function test_student_cannot_bulk_grade(): void
    {
        [$offering, $payload] = $this->offeringWithBulkPayload(User::factory()->instructor()->create());
        $student = Student::factory()->create();
        Sanctum::actingAs(User::find($student->user_id));

        $this->putJson("/api/v1/course-offerings/{$offering->id}/grades", $payload)->assertForbidden();
    }

    // Grading deadlines

    /**
     * @return array<string, string>
     */
    private function deadlinePayload(): array
    {
        return [
            'midterm_grading_deadline' => now()->addDays(20)->toDateString(),
            'final_grading_deadline' => now()->addDays(40)->toDateString(),
            'inc_completion_deadline' => now()->addDays(60)->toDateString(),
        ];
    }

    public function test_grading_deadlines_require_authentication(): void
    {
        $term = AcademicTerm::factory()->create();

        $this->patchJson("/api/v1/academic-terms/{$term->id}/grading-deadlines", $this->deadlinePayload())
            ->assertUnauthorized();
    }

    public function test_administrator_and_registrar_can_update_grading_deadlines(): void
    {
        foreach (['administrator', 'registrar'] as $role) {
            $term = AcademicTerm::factory()->create();
            Sanctum::actingAs(User::factory()->create(['role' => $role]));

            $this->patchJson("/api/v1/academic-terms/{$term->id}/grading-deadlines", $this->deadlinePayload())
                ->assertOk();

            $this->assertSame(
                now()->addDays(20)->toDateString(),
                $term->fresh()->midterm_grading_deadline->toDateString(),
                "{$role} should be able to change the midterm deadline",
            );
        }
    }

    public function test_instructor_and_student_cannot_update_grading_deadlines(): void
    {
        $term = AcademicTerm::factory()->create();
        $student = Student::factory()->create();

        foreach ([User::factory()->instructor()->create(), User::find($student->user_id)] as $user) {
            Sanctum::actingAs($user);

            $this->patchJson("/api/v1/academic-terms/{$term->id}/grading-deadlines", $this->deadlinePayload())
                ->assertForbidden();
        }
    }
}
