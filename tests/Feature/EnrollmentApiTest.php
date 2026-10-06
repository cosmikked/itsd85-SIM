<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnrollmentApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function protectedRoutes(): array
    {
        return [
            'index' => ['getJson', '/api/v1/enrollments'],
            'show' => ['getJson', '/api/v1/enrollments/{enrollment}'],
            'store' => ['postJson', '/api/v1/enrollments'],
            'update' => ['patchJson', '/api/v1/enrollments/{enrollment}'],
            'destroy' => ['deleteJson', '/api/v1/enrollments/{enrollment}'],
        ];
    }

    #[DataProvider('protectedRoutes')]
    public function test_returns_401_without_a_token_on_every_enrollment_route(string $method, string $uri): void
    {
        $enrollment = Enrollment::factory()->create();

        $response = $this->{$method}(str_replace('{enrollment}', (string) $enrollment->id, $uri));

        $response->assertUnauthorized();
    }

    public function test_index_returns_200_with_the_enrollments(): void
    {
        $this->actingAsAdministrator();
        $program = Program::factory()->create();
        $offering = CourseOffering::factory()->create();
        Enrollment::factory()->count(3)->recycle($program)->create([
            'course_offering_id' => $offering->id,
        ]);

        $response = $this->getJson('/api/v1/enrollments');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Enrollments retrieved successfully.')
            ->assertJsonCount(3, 'data');
    }

    public function test_index_returns_15_enrollments_per_page(): void
    {
        $this->actingAsAdministrator();
        $program = Program::factory()->create();
        $offering = CourseOffering::factory()->create();
        Enrollment::factory()->count(16)->recycle($program)->create([
            'course_offering_id' => $offering->id,
        ]);

        $response = $this->getJson('/api/v1/enrollments');

        $response->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 16);
    }

    public function test_index_accepts_per_page_parameter(): void
    {
        $this->actingAsAdministrator();
        $program = Program::factory()->create();
        $offering = CourseOffering::factory()->create();
        Enrollment::factory()->count(16)->recycle($program)->create([
            'course_offering_id' => $offering->id,
        ]);

        $response = $this->getJson('/api/v1/enrollments?per_page=5');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 16);
    }

    public function test_index_can_filter_by_exact_matches(): void
    {
        $this->actingAsAdministrator();
        $program = Program::factory()->create();
        $student = Student::factory()->recycle($program)->create();
        $offering = CourseOffering::factory()->create();
        $offering2 = CourseOffering::factory()->create();
        Enrollment::factory()->create(['status' => 'enrolled', 'student_id' => $student->id, 'course_offering_id' => $offering->id]);
        Enrollment::factory()->create(['status' => 'dropped', 'student_id' => $student->id, 'course_offering_id' => $offering2->id]);

        $response = $this->getJson('/api/v1/enrollments?status=enrolled&student_id='.$student->id.'&course_offering_id='.$offering->id);

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_show_returns_200_with_the_enrollment(): void
    {
        $this->actingAsAdministrator();
        $enrollment = Enrollment::factory()->create(['status' => 'completed']);

        $response = $this->getJson("/api/v1/enrollments/{$enrollment->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Enrollment retrieved successfully.')
            ->assertJsonPath('data.id', $enrollment->id)
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_store_with_valid_data_returns_201_and_creates_the_enrollment(): void
    {
        $this->actingAsAdministrator();

        $student = Student::factory()->create();
        $offering = CourseOffering::factory()->create(['status' => 'open', 'capacity' => 10]);

        $validDate = $offering->academicTerm->start_date->copy()->addDay()->toDateString();

        $response = $this->postJson('/api/v1/enrollments', [
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'enrollment_date' => $validDate,
            'status' => 'enrolled',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'enrolled')
            ->assertJsonPath('data.enrollment_date', $validDate);

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_store_when_student_already_enrolled_in_section_returns_422(): void
    {
        $this->actingAsAdministrator();

        $student = Student::factory()->create();
        $offering = CourseOffering::factory()->create(['status' => 'open', 'capacity' => 10]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
        ]);

        $response = $this->postJson('/api/v1/enrollments', [
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'enrollment_date' => '2024-08-16',
        ]);

        $this->assertValidationFailed($response, ['course_offering_id']);
    }

    public function test_store_when_course_offering_is_full_returns_422(): void
    {
        $this->actingAsAdministrator();

        $offering = CourseOffering::factory()->create(['status' => 'open', 'capacity' => 2]);
        $program = Program::factory()->create();

        // Fill the class to its capacity
        Enrollment::factory()->count(2)->recycle($program)->create([
            'course_offering_id' => $offering->id,
            'status' => 'enrolled',
        ]);

        $student = Student::factory()->create();
        $validDate = $offering->academicTerm->start_date->copy()->addDay()->toDateString();

        $response = $this->postJson('/api/v1/enrollments', [
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'enrollment_date' => $validDate,
        ]);

        $this->assertValidationFailed($response, ['course_offering_id']);
    }

    public function test_store_allows_enrollment_if_previous_students_dropped(): void
    {
        $this->actingAsAdministrator();

        $offering = CourseOffering::factory()->create(['status' => 'open', 'capacity' => 2]);
        $program = Program::factory()->create();

        // Create 2 dropped students and 1 enrolled student (total 3 records, but only 1 active seat taken)
        Enrollment::factory()->count(2)->recycle($program)->create([
            'course_offering_id' => $offering->id,
            'status' => 'dropped',
        ]);
        Enrollment::factory()->recycle($program)->create([
            'course_offering_id' => $offering->id,
            'status' => 'enrolled',
        ]);

        $student = Student::factory()->create();
        $validDate = $offering->academicTerm->start_date->copy()->addDay()->toDateString();

        $response = $this->postJson('/api/v1/enrollments', [
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'enrollment_date' => $validDate,
            'status' => 'enrolled',
        ]);

        $response->assertCreated();
    }

    public function test_update_status_to_enrolled_fails_if_capacity_is_full(): void
    {
        $this->actingAsAdministrator();

        $offering = CourseOffering::factory()->create(['status' => 'open', 'capacity' => 1]);
        $program = Program::factory()->create();

        // One student is currently enrolled (capacity full)
        Enrollment::factory()->recycle($program)->create([
            'course_offering_id' => $offering->id,
            'status' => 'enrolled',
        ]);

        // Another student is currently dropped
        $droppedEnrollment = Enrollment::factory()->recycle($program)->create([
            'course_offering_id' => $offering->id,
            'status' => 'dropped',
        ]);

        // Attempt to update the dropped student back to enrolled
        $response = $this->patchJson("/api/v1/enrollments/{$droppedEnrollment->id}", [
            'status' => 'enrolled',
        ]);

        $this->assertValidationFailed($response, ['status']);
    }

    public function test_store_when_course_offering_status_is_not_open_returns_422(): void
    {
        $this->actingAsAdministrator();

        $offering = CourseOffering::factory()->create(['status' => 'closed', 'capacity' => 40]);
        $student = Student::factory()->create();

        $response = $this->postJson('/api/v1/enrollments', [
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'enrollment_date' => '2024-08-16',
        ]);

        $this->assertValidationFailed($response, ['course_offering_id']);
    }

    public function test_update_with_valid_data_returns_200_and_updates_the_enrollment(): void
    {
        $this->actingAsAdministrator();
        $enrollment = Enrollment::factory()->create(['status' => 'enrolled']);

        $validDate = $enrollment->courseOffering->academicTerm->start_date->copy()->addDay()->toDateString();

        $response = $this->patchJson("/api/v1/enrollments/{$enrollment->id}", [
            'status' => 'completed',
            'enrollment_date' => $validDate,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.enrollment_date', $validDate);

        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'status' => 'completed',
        ]);
    }

    public function test_update_rejects_changes_to_student_id_or_course_offering_id(): void
    {
        $this->actingAsAdministrator();
        $enrollment = Enrollment::factory()->create();

        $newStudent = Student::factory()->create();
        $newOffering = CourseOffering::factory()->create();

        $response = $this->patchJson("/api/v1/enrollments/{$enrollment->id}", [
            'student_id' => $newStudent->id,
            'course_offering_id' => $newOffering->id,
        ]);

        // Depending on implementation, you might return 422 or just completely ignore the fields.
        // We will assert that the database record was not changed regardless of the response code.
        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'course_offering_id' => $enrollment->course_offering_id,
        ]);
    }

    public function test_destroy_returns_204_and_deletes_the_enrollment(): void
    {
        $this->actingAsAdministrator();
        $enrollment = Enrollment::factory()->create(['status' => 'enrolled']);

        $response = $this->deleteJson("/api/v1/enrollments/{$enrollment->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('enrollments', ['id' => $enrollment->id]);
    }

    public function test_destroy_returns_409_if_enrollment_is_completed(): void
    {
        $this->actingAsAdministrator();
        $enrollment = Enrollment::factory()->create(['status' => 'completed']);

        $response = $this->deleteJson("/api/v1/enrollments/{$enrollment->id}");

        $response->assertConflict();
        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id]);
    }

    public function test_store_with_enrollment_date_before_term_start_returns_422(): void
    {
        $this->actingAsAdministrator();

        $term = AcademicTerm::factory()->create();

        $offering = CourseOffering::factory()->create([
            'academic_term_id' => $term->id,
            'status' => 'open',
            'capacity' => 10,
        ]);

        $student = Student::factory()->create();

        $invalidDate = $term->start_date->copy()->subDay()->toDateString();

        $response = $this->postJson('/api/v1/enrollments', [
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'enrollment_date' => $invalidDate,
        ]);

        $this->assertValidationFailed($response, ['enrollment_date']);
    }

    public function test_update_with_enrollment_date_before_term_start_returns_422(): void
    {
        $this->actingAsAdministrator();

        $term = AcademicTerm::factory()->create();

        $offering = CourseOffering::factory()->create([
            'academic_term_id' => $term->id,
        ]);

        $enrollment = Enrollment::factory()->create([
            'course_offering_id' => $offering->id,
        ]);

        $invalidDate = $term->start_date->copy()->subDay()->toDateString();

        $response = $this->patchJson("/api/v1/enrollments/{$enrollment->id}", [
            'enrollment_date' => $invalidDate,
        ]);

        $this->assertValidationFailed($response, ['enrollment_date']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidDateFormats(): array
    {
        return [
            'random string' => ['not-a-date'],
            'missing day' => ['2024-09'],
            'american format (MM/DD/YYYY)' => ['09/15/2024'],
            'european format (DD/MM/YYYY)' => ['15/09/2024'],
            'wrong separator' => ['2024.09.15'],
        ];
    }

    #[DataProvider('invalidDateFormats')]
    public function test_store_rejects_invalid_enrollment_date_formats(string $invalidDate): void
    {
        $this->actingAsAdministrator();

        $term = AcademicTerm::factory()->create();

        $offering = CourseOffering::factory()->create([
            'academic_term_id' => $term->id,
            'status' => 'open',
            'capacity' => 10,
        ]);

        $student = Student::factory()->create();

        $response = $this->postJson('/api/v1/enrollments', [
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'enrollment_date' => $invalidDate,
        ]);

        $this->assertValidationFailed($response, ['enrollment_date']);
    }

    #[DataProvider('invalidDateFormats')]
    public function test_update_rejects_invalid_enrollment_date_formats(string $invalidDate): void
    {
        $this->actingAsAdministrator();

        $term = AcademicTerm::factory()->create();

        $offering = CourseOffering::factory()->create([
            'academic_term_id' => $term->id,
        ]);

        $enrollment = Enrollment::factory()->create([
            'course_offering_id' => $offering->id,
        ]);

        $response = $this->patchJson("/api/v1/enrollments/{$enrollment->id}", [
            'enrollment_date' => $invalidDate,
        ]);

        $this->assertValidationFailed($response, ['enrollment_date']);
    }

    private function actingAsAdministrator(): void
    {
        Sanctum::actingAs(User::factory()->administrator()->create());
    }

    /**
     * @param  array<int, string>  $fields
     */
    private function assertValidationFailed(TestResponse $response, array $fields): void
    {
        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonValidationErrors($fields);
    }
}
