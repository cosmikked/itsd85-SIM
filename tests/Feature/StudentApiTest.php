<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function protectedRoutes(): array
    {
        return [
            'index' => ['getJson', '/api/v1/students'],
            'show' => ['getJson', '/api/v1/students/{student}'],
            'store' => ['postJson', '/api/v1/students'],
            'update' => ['putJson', '/api/v1/students/{student}'],
            'destroy' => ['deleteJson', '/api/v1/students/{student}'],
        ];
    }

    /**
     * @return array<string, array{int|float|string}>
     */
    public static function invalidYearLevels(): array
    {
        return [
            'zero' => [0],
            'above the maximum' => [5],
            'not a number' => ['two'],
            'decimal' => [2.5],
        ];
    }

    /**
     * @return array<string, array{int}>
     */
    public static function boundaryYearLevels(): array
    {
        return [
            'minimum' => [1],
            'maximum' => [4],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function uniqueFields(): array
    {
        return [
            'student_number' => ['student_number'],
            'email' => ['email'],
            'contact_number' => ['contact_number'],
        ];
    }

    #[DataProvider('protectedRoutes')]
    public function test_returns_401_without_a_token_on_every_student_route(string $method, string $uri): void
    {
        $student = Student::factory()->create();

        $response = $this->{$method}(str_replace('{student}', (string) $student->id, $uri));

        $response->assertUnauthorized();
    }

    public function test_index_returns_200_with_the_students(): void
    {
        $this->actingAsAdministrator();
        Student::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/students');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Students retrieved successfully.')
            ->assertJsonCount(3, 'data');
    }

    public function test_index_returns_15_students_per_page(): void
    {
        $this->actingAsAdministrator();
        // ProgramFactory can only produce 12 distinct programs, and Student::factory()
        // spawns a new one per student by default, so share a single program instead.
        $program = Program::factory()->create();
        Student::factory()->count(16)->create(['program_id' => $program->id]);

        $response = $this->getJson('/api/v1/students');

        $response->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 16);
    }

    public function test_show_returns_200_with_the_student(): void
    {
        $this->actingAsAdministrator();
        $program = Program::factory()->create();
        $student = Student::factory()->create([
            'student_number' => '2026-00001',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'program_id' => $program->id,
        ]);

        $response = $this->getJson("/api/v1/students/{$student->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Student retrieved successfully.')
            ->assertJsonPath('data.id', $student->id)
            ->assertJsonPath('data.student_number', '2026-00001')
            ->assertJsonPath('data.first_name', 'Ada')
            ->assertJsonPath('data.last_name', 'Lovelace')
            ->assertJsonPath('data.program_id', $program->id);
    }

    public function test_show_returns_404_for_an_unknown_student(): void
    {
        $this->actingAsAdministrator();

        $response = $this->getJson('/api/v1/students/999');

        $response->assertNotFound();
    }

    public function test_store_with_valid_data_returns_201_and_creates_the_student(): void
    {
        $this->actingAsAdministrator();
        $program = Program::factory()->create();

        $response = $this->postJson('/api/v1/students', $this->validPayload($program, ['status' => 'irregular']));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Student created successfully.')
            ->assertJsonPath('data.student_number', '2026-00001')
            ->assertJsonPath('data.program_id', $program->id)
            ->assertJsonPath('data.status', 'irregular');
        $this->assertDatabaseHas('students', [
            'student_number' => '2026-00001',
            'email' => 'ada@example.com',
            'status' => 'irregular',
        ]);
    }

    public function test_store_without_a_status_defaults_to_regular(): void
    {
        $this->actingAsAdministrator();
        $program = Program::factory()->create();

        $response = $this->postJson('/api/v1/students', $this->validPayload($program));

        $response->assertCreated()->assertJsonPath('data.status', 'regular');
        $this->assertDatabaseHas('students', ['student_number' => '2026-00001', 'status' => 'regular']);
    }

    public function test_store_with_an_empty_payload_returns_422_for_the_required_fields(): void
    {
        $this->actingAsAdministrator();

        $response = $this->postJson('/api/v1/students', []);

        $this->assertValidationFailed($response, [
            'student_number', 'first_name', 'last_name', 'birth_date',
            'email', 'contact_number', 'program_id', 'year_level',
        ]);
        $this->assertDatabaseCount('students', 0);
    }

    #[DataProvider('uniqueFields')]
    public function test_store_with_a_duplicate_unique_field_returns_422(string $field): void
    {
        $this->actingAsAdministrator();
        $program = Program::factory()->create();
        $existing = Student::factory()->create();

        $response = $this->postJson('/api/v1/students', $this->validPayload($program, [$field => $existing->{$field}]));

        $this->assertValidationFailed($response, [$field]);
        $this->assertDatabaseCount('students', 1);
    }

    public function test_store_with_an_invalid_email_format_returns_422(): void
    {
        $this->actingAsAdministrator();
        $program = Program::factory()->create();

        $response = $this->postJson('/api/v1/students', $this->validPayload($program, ['email' => 'not-an-email']));

        $this->assertValidationFailed($response, ['email']);
        $this->assertDatabaseCount('students', 0);
    }

    public function test_store_with_a_nonexistent_program_returns_422(): void
    {
        $this->actingAsAdministrator();

        $response = $this->postJson('/api/v1/students', $this->validPayload(null, ['program_id' => 999]));

        $this->assertValidationFailed($response, ['program_id']);
        $this->assertDatabaseCount('students', 0);
    }

    #[DataProvider('invalidYearLevels')]
    public function test_store_rejects_a_year_level_outside_the_allowed_range(int|float|string $yearLevel): void
    {
        $this->actingAsAdministrator();
        $program = Program::factory()->create();

        $response = $this->postJson('/api/v1/students', $this->validPayload($program, ['year_level' => $yearLevel]));

        $this->assertValidationFailed($response, ['year_level']);
        $this->assertDatabaseCount('students', 0);
    }

    #[DataProvider('boundaryYearLevels')]
    public function test_store_accepts_year_levels_at_the_range_boundaries(int $yearLevel): void
    {
        $this->actingAsAdministrator();
        $program = Program::factory()->create();

        $response = $this->postJson('/api/v1/students', $this->validPayload($program, ['year_level' => $yearLevel]));

        $response->assertCreated();
        $this->assertDatabaseHas('students', ['student_number' => '2026-00001', 'year_level' => $yearLevel]);
    }

    public function test_store_with_an_invalid_status_returns_422(): void
    {
        $this->actingAsAdministrator();
        $program = Program::factory()->create();

        $response = $this->postJson('/api/v1/students', $this->validPayload($program, ['status' => 'graduated']));

        $this->assertValidationFailed($response, ['status']);
        $this->assertDatabaseCount('students', 0);
    }

    public function test_store_with_a_future_birth_date_returns_422(): void
    {
        $this->actingAsAdministrator();
        $program = Program::factory()->create();

        $response = $this->postJson('/api/v1/students', $this->validPayload($program, ['birth_date' => '2099-01-01']));

        $this->assertValidationFailed($response, ['birth_date']);
        $this->assertDatabaseCount('students', 0);
    }

    public function test_store_with_todays_birth_date_returns_422(): void
    {
        $this->actingAsAdministrator();
        $program = Program::factory()->create();

        $response = $this->postJson('/api/v1/students', $this->validPayload($program, ['birth_date' => now()->toDateString()]));

        $this->assertValidationFailed($response, ['birth_date']);
        $this->assertDatabaseCount('students', 0);
    }

    public function test_update_with_valid_data_returns_200_and_updates_the_student(): void
    {
        $this->actingAsAdministrator();
        $student = Student::factory()->create(['first_name' => 'Old', 'year_level' => 1]);

        $response = $this->putJson("/api/v1/students/{$student->id}", [
            'first_name' => 'New',
            'year_level' => 3,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Student updated successfully.')
            ->assertJsonPath('data.first_name', 'New')
            ->assertJsonPath('data.year_level', 3);
        $this->assertDatabaseHas('students', ['id' => $student->id, 'first_name' => 'New', 'year_level' => 3]);
    }

    #[DataProvider('uniqueFields')]
    public function test_update_resending_its_own_unique_field_returns_200(string $field): void
    {
        $this->actingAsAdministrator();
        $student = Student::factory()->create();

        $response = $this->putJson("/api/v1/students/{$student->id}", [$field => $student->{$field}, 'first_name' => 'Renamed']);

        $response->assertOk()->assertJsonPath('data.first_name', 'Renamed');
    }

    #[DataProvider('uniqueFields')]
    public function test_update_with_another_students_unique_field_returns_422(string $field): void
    {
        $this->actingAsAdministrator();
        $other = Student::factory()->create();
        $student = Student::factory()->create();

        $response = $this->putJson("/api/v1/students/{$student->id}", [$field => $other->{$field}]);

        $this->assertValidationFailed($response, [$field]);
    }

    public function test_update_with_a_year_level_outside_the_allowed_range_returns_422(): void
    {
        $this->actingAsAdministrator();
        $student = Student::factory()->create(['year_level' => 2]);

        $response = $this->putJson("/api/v1/students/{$student->id}", ['year_level' => 5]);

        $this->assertValidationFailed($response, ['year_level']);
        $this->assertDatabaseHas('students', ['id' => $student->id, 'year_level' => 2]);
    }

    public function test_update_with_only_a_status_returns_200_and_leaves_the_other_fields_unchanged(): void
    {
        $this->actingAsAdministrator();
        $student = Student::factory()->create(['first_name' => 'Ada', 'status' => 'regular']);

        $response = $this->putJson("/api/v1/students/{$student->id}", ['status' => 'irregular']);

        $response->assertOk()->assertJsonPath('data.status', 'irregular');
        $this->assertDatabaseHas('students', ['id' => $student->id, 'first_name' => 'Ada', 'status' => 'irregular']);
    }

    public function test_destroy_returns_204_and_deletes_the_student(): void
    {
        $this->actingAsAdministrator();
        $student = Student::factory()->create();

        $response = $this->deleteJson("/api/v1/students/{$student->id}");

        $response->assertNoContent();
        $this->assertModelMissing($student);
    }

    public function test_destroy_returns_409_when_the_student_has_enrollments(): void
    {
        $this->actingAsAdministrator();
        $student = Student::factory()->create();
        Enrollment::factory()->create(['student_id' => $student->id]);

        $response = $this->deleteJson("/api/v1/students/{$student->id}");

        $response->assertConflict();
        $this->assertModelExists($student);
    }

    private function actingAsAdministrator(): void
    {
        Sanctum::actingAs(User::factory()->administrator()->create());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(?Program $program, array $overrides = []): array
    {
        return array_merge([
            'student_number' => '2026-00001',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'birth_date' => '2005-01-01',
            'email' => 'ada@example.com',
            'contact_number' => '09171234567',
            'program_id' => $program?->id,
            'year_level' => 1,
        ], $overrides);
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
