<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProgramApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function protectedRoutes(): array
    {
        return [
            'index' => ['getJson', '/api/v1/programs'],
            'show' => ['getJson', '/api/v1/programs/{program}'],
            'store' => ['postJson', '/api/v1/programs'],
            'update' => ['putJson', '/api/v1/programs/{program}'],
            'destroy' => ['deleteJson', '/api/v1/programs/{program}'],
        ];
    }

    #[DataProvider('protectedRoutes')]
    public function test_returns_401_without_a_token_on_every_program_route(string $method, string $uri): void
    {
        $program = Program::factory()->create();

        $response = $this->{$method}(str_replace('{program}', (string) $program->id, $uri));

        $response->assertUnauthorized();
    }

    public function test_index_returns_200_with_the_programs(): void
    {
        $this->actingAsAdministrator();
        Program::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/programs');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Programs retrieved successfully.')
            ->assertJsonCount(3, 'data');
    }

    public function test_index_returns_15_programs_per_page(): void
    {
        $this->actingAsAdministrator();
        // ProgramFactory can only produce 12 distinct programs, so insert 16 rows directly.
        Program::query()->insert(array_map(
            fn (int $number): array => ['code' => "PRG{$number}", 'name' => "Program {$number}", 'status' => 'active'],
            range(1, 16),
        ));

        $response = $this->getJson('/api/v1/programs');

        $response->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 16);
    }

    public function test_show_returns_200_with_the_program(): void
    {
        $this->actingAsAdministrator();
        $program = Program::factory()->create([
            'code' => 'BSCS',
            'name' => 'Bachelor of Science in Computer Science',
        ]);

        $response = $this->getJson("/api/v1/programs/{$program->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Program retrieved successfully.')
            ->assertJsonPath('data.id', $program->id)
            ->assertJsonPath('data.code', 'BSCS')
            ->assertJsonPath('data.name', 'Bachelor of Science in Computer Science');
    }

    public function test_show_returns_404_for_an_unknown_program(): void
    {
        $this->actingAsAdministrator();

        $response = $this->getJson('/api/v1/programs/999');

        $response->assertNotFound();
    }

    public function test_store_with_valid_data_returns_201_and_creates_the_program(): void
    {
        $this->actingAsAdministrator();

        $response = $this->postJson('/api/v1/programs', [
            'code' => 'BSCS',
            'name' => 'Bachelor of Science in Computer Science',
            'description' => 'Focuses on computing theory and software development.',
            'status' => 'inactive',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Program created successfully.')
            ->assertJsonPath('data.code', 'BSCS')
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('programs', [
            'code' => 'BSCS',
            'name' => 'Bachelor of Science in Computer Science',
            'status' => 'inactive',
        ]);
    }

    public function test_store_with_an_empty_payload_returns_422_for_the_required_fields(): void
    {
        $this->actingAsAdministrator();

        $response = $this->postJson('/api/v1/programs', []);

        $this->assertValidationFailed($response, ['code', 'name']);
        $this->assertDatabaseCount('programs', 0);
    }

    public function test_store_with_a_duplicate_code_returns_422(): void
    {
        $this->actingAsAdministrator();
        Program::factory()->create(['code' => 'BSCS']);

        $response = $this->postJson('/api/v1/programs', [
            'code' => 'BSCS',
            'name' => 'Another Program',
        ]);

        $this->assertValidationFailed($response, ['code']);
        $this->assertDatabaseCount('programs', 1);
    }

    public function test_store_with_an_invalid_status_returns_422(): void
    {
        $this->actingAsAdministrator();

        $response = $this->postJson('/api/v1/programs', [
            'code' => 'BSCS',
            'name' => 'Bachelor of Science in Computer Science',
            'status' => 'archived',
        ]);

        $this->assertValidationFailed($response, ['status']);
        $this->assertDatabaseCount('programs', 0);
    }

    public function test_store_without_a_description_returns_201(): void
    {
        $this->actingAsAdministrator();

        $response = $this->postJson('/api/v1/programs', [
            'code' => 'BSCS',
            'name' => 'Bachelor of Science in Computer Science',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('programs', ['code' => 'BSCS', 'description' => null]);
    }

    public function test_update_with_valid_data_returns_200_and_updates_the_program(): void
    {
        $this->actingAsAdministrator();
        $program = Program::factory()->create(['code' => 'BSCS', 'name' => 'Old Name']);

        $response = $this->putJson("/api/v1/programs/{$program->id}", [
            'code' => 'BSCS-2',
            'name' => 'New Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Program updated successfully.')
            ->assertJsonPath('data.code', 'BSCS-2')
            ->assertJsonPath('data.name', 'New Name');
        $this->assertDatabaseHas('programs', ['id' => $program->id, 'code' => 'BSCS-2', 'name' => 'New Name']);
    }

    public function test_update_resending_its_own_code_returns_200(): void
    {
        $this->actingAsAdministrator();
        $program = Program::factory()->create(['code' => 'BSCS']);

        $response = $this->putJson("/api/v1/programs/{$program->id}", [
            'code' => 'BSCS',
            'name' => 'Renamed Program',
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'Renamed Program');
    }

    public function test_update_with_another_programs_code_returns_422(): void
    {
        $this->actingAsAdministrator();
        Program::factory()->create(['code' => 'BSCS']);
        $program = Program::factory()->create(['code' => 'BSIT']);

        $response = $this->putJson("/api/v1/programs/{$program->id}", [
            'code' => 'BSCS',
            'name' => 'Renamed Program',
        ]);

        $this->assertValidationFailed($response, ['code']);
        $this->assertDatabaseHas('programs', ['id' => $program->id, 'code' => 'BSIT']);
    }

    public function test_destroy_returns_204_and_deletes_the_program(): void
    {
        $this->actingAsAdministrator();
        $program = Program::factory()->create();

        $response = $this->deleteJson("/api/v1/programs/{$program->id}");
        $response->dump();

        $response->assertNoContent();
        $this->assertModelMissing($program);
    }

    public function test_destroy_returns_409_when_the_program_has_students(): void
    {
        $this->actingAsAdministrator();
        $program = Program::factory()->create();
        Student::factory()->create(['program_id' => $program->id]);

        $response = $this->deleteJson("/api/v1/programs/{$program->id}");

        $response->assertConflict();
        $this->assertModelExists($program);
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
