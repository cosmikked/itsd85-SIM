<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\CourseOffering;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AcademicTermApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function protectedRoutes(): array
    {
        return [
            'index' => ['getJson', '/api/v1/academic-terms'],
            'show' => ['getJson', '/api/v1/academic-terms/{academic_term}'],
            'store' => ['postJson', '/api/v1/academic-terms'],
            'update' => ['putJson', '/api/v1/academic-terms/{academic_term}'],
            'destroy' => ['deleteJson', '/api/v1/academic-terms/{academic_term}'],
        ];
    }

    /**
     * An existing term is ('2026-2027', 'First Semester'); these pairs must still be accepted.
     *
     * @return array<string, array{string, string}>
     */
    public static function pairsThatDifferFromTheExistingTerm(): array
    {
        return [
            'same year, different term' => ['2026-2027', 'Second Semester'],
            'different year, same term' => ['2027-2028', 'First Semester'],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function endDatesNotAfterTheStartDate(): array
    {
        return [
            'before the start date' => ['2026-12-18', '2026-08-03'],
            'same day as the start date' => ['2026-08-03', '2026-08-03'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedDates(): array
    {
        return [
            'text' => ['not a date'],
            'day first' => ['03/08/2026'],
            'impossible date' => ['2026-13-45'],
        ];
    }

    #[DataProvider('protectedRoutes')]
    public function test_returns_401_without_a_token_on_every_academic_term_route(string $method, string $uri): void
    {
        $academicTerm = AcademicTerm::factory()->create();

        $response = $this->{$method}(str_replace('{academic_term}', (string) $academicTerm->id, $uri));

        $response->assertUnauthorized();
    }

    public function test_index_returns_200_with_the_academic_terms(): void
    {
        $this->actingAsAdministrator();
        AcademicTerm::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/academic-terms');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Academic terms retrieved successfully.')
            ->assertJsonCount(3, 'data');
    }

    public function test_index_returns_15_academic_terms_per_page(): void
    {
        $this->actingAsAdministrator();
        // AcademicTermFactory can only produce 7 distinct terms, so insert 16 rows directly.
        AcademicTerm::query()->insert(array_map(
            fn (int $year): array => [
                'academic_year' => sprintf('%d-%d', $year, $year + 1),
                'term' => 'First Semester',
                'start_date' => sprintf('%d-08-03', $year),
                'end_date' => sprintf('%d-12-18', $year),
                'status' => 'active',
            ],
            range(2000, 2015),
        ));

        $response = $this->getJson('/api/v1/academic-terms');

        $response->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 16);
    }

    public function test_show_returns_200_with_the_academic_term(): void
    {
        $this->actingAsAdministrator();
        $academicTerm = AcademicTerm::factory()->create([
            'academic_year' => '2026-2027',
            'term' => 'First Semester',
            'start_date' => '2026-08-03',
            'end_date' => '2026-12-18',
        ]);

        $response = $this->getJson("/api/v1/academic-terms/{$academicTerm->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Academic term retrieved successfully.')
            ->assertJsonPath('data.id', $academicTerm->id)
            ->assertJsonPath('data.academic_year', '2026-2027')
            ->assertJsonPath('data.term', 'First Semester')
            ->assertJsonPath('data.start_date', '2026-08-03')
            ->assertJsonPath('data.end_date', '2026-12-18');
    }

    public function test_show_returns_404_for_an_unknown_academic_term(): void
    {
        $this->actingAsAdministrator();

        $response = $this->getJson('/api/v1/academic-terms/999');

        $response->assertNotFound();
    }

    public function test_store_with_valid_data_returns_201_and_creates_the_academic_term(): void
    {
        $this->actingAsAdministrator();

        $response = $this->postJson('/api/v1/academic-terms', $this->validPayload(['status' => 'inactive']));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Academic term created successfully.')
            ->assertJsonPath('data.academic_year', '2026-2027')
            ->assertJsonPath('data.term', 'First Semester')
            ->assertJsonPath('data.start_date', '2026-08-03')
            ->assertJsonPath('data.end_date', '2026-12-18')
            ->assertJsonPath('data.status', 'inactive');
        $this->assertDatabaseHas('academic_terms', [
            'academic_year' => '2026-2027',
            'term' => 'First Semester',
            'status' => 'inactive',
        ]);
        $academicTerm = AcademicTerm::query()->firstOrFail();
        $this->assertSame('2026-08-03', $academicTerm->start_date->toDateString());
        $this->assertSame('2026-12-18', $academicTerm->end_date->toDateString());
    }

    public function test_store_without_a_status_defaults_to_active(): void
    {
        $this->actingAsAdministrator();

        $response = $this->postJson('/api/v1/academic-terms', $this->validPayload());

        $response->assertCreated()->assertJsonPath('data.status', 'active');
        $this->assertDatabaseHas('academic_terms', ['academic_year' => '2026-2027', 'status' => 'active']);
    }

    public function test_store_with_an_empty_payload_returns_422_for_the_required_fields(): void
    {
        $this->actingAsAdministrator();

        $response = $this->postJson('/api/v1/academic-terms', []);

        $this->assertValidationFailed($response, ['academic_year', 'term', 'start_date', 'end_date']);
        $this->assertDatabaseCount('academic_terms', 0);
    }

    public function test_store_with_a_duplicate_academic_year_and_term_returns_422(): void
    {
        $this->actingAsAdministrator();
        AcademicTerm::factory()->create(['academic_year' => '2026-2027', 'term' => 'First Semester']);

        $response = $this->postJson('/api/v1/academic-terms', $this->validPayload());

        $this->assertValidationFailed($response, ['term']);
        $this->assertDatabaseCount('academic_terms', 1);
    }

    #[DataProvider('pairsThatDifferFromTheExistingTerm')]
    public function test_store_accepts_a_pair_that_differs_from_an_existing_term(string $academicYear, string $term): void
    {
        $this->actingAsAdministrator();
        AcademicTerm::factory()->create(['academic_year' => '2026-2027', 'term' => 'First Semester']);

        $response = $this->postJson('/api/v1/academic-terms', $this->validPayload([
            'academic_year' => $academicYear,
            'term' => $term,
        ]));

        $response->assertCreated();
        $this->assertDatabaseCount('academic_terms', 2);
    }

    public function test_store_with_an_invalid_term_returns_422(): void
    {
        $this->actingAsAdministrator();

        $response = $this->postJson('/api/v1/academic-terms', $this->validPayload(['term' => 'Summer']));

        $this->assertValidationFailed($response, ['term']);
        $this->assertDatabaseCount('academic_terms', 0);
    }

    public function test_store_with_an_invalid_status_returns_422(): void
    {
        $this->actingAsAdministrator();

        $response = $this->postJson('/api/v1/academic-terms', $this->validPayload(['status' => 'archived']));

        $this->assertValidationFailed($response, ['status']);
        $this->assertDatabaseCount('academic_terms', 0);
    }

    public function test_store_with_a_non_consecutive_academic_year_returns_422(): void
    {
        $this->actingAsAdministrator();

        $response = $this->postJson('/api/v1/academic-terms', $this->validPayload(['academic_year' => '2026-2028']));

        $this->assertValidationFailed($response, ['academic_year']);
        $response->assertJsonPath(
            'errors.academic_year.0',
            'The academic year must be two consecutive years in the format YYYY-YYYY, such as 2026-2027.',
        );
        $this->assertDatabaseCount('academic_terms', 0);
    }

    #[DataProvider('endDatesNotAfterTheStartDate')]
    public function test_store_rejects_an_end_date_that_is_not_after_the_start_date(string $startDate, string $endDate): void
    {
        $this->actingAsAdministrator();

        $response = $this->postJson('/api/v1/academic-terms', $this->validPayload([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]));

        $this->assertValidationFailed($response, ['end_date']);
        $this->assertDatabaseCount('academic_terms', 0);
    }

    #[DataProvider('malformedDates')]
    public function test_store_rejects_a_start_date_in_the_wrong_format(string $startDate): void
    {
        $this->actingAsAdministrator();

        $response = $this->postJson('/api/v1/academic-terms', $this->validPayload(['start_date' => $startDate]));

        $this->assertValidationFailed($response, ['start_date']);
        $this->assertDatabaseCount('academic_terms', 0);
    }

    public function test_update_with_valid_data_returns_200_and_updates_the_academic_term(): void
    {
        $this->actingAsAdministrator();
        $academicTerm = AcademicTerm::factory()->create([
            'academic_year' => '2026-2027',
            'term' => 'First Semester',
            'start_date' => '2026-08-03',
            'end_date' => '2026-12-18',
            'status' => 'active',
        ]);

        $response = $this->putJson("/api/v1/academic-terms/{$academicTerm->id}", [
            'academic_year' => '2027-2028',
            'term' => 'Second Semester',
            'start_date' => '2028-01-10',
            'end_date' => '2028-05-19',
            'status' => 'inactive',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Academic term updated successfully.')
            ->assertJsonPath('data.academic_year', '2027-2028')
            ->assertJsonPath('data.term', 'Second Semester')
            ->assertJsonPath('data.start_date', '2028-01-10')
            ->assertJsonPath('data.end_date', '2028-05-19')
            ->assertJsonPath('data.status', 'inactive');
        $academicTerm->refresh();
        $this->assertSame('2027-2028', $academicTerm->academic_year);
        $this->assertSame('Second Semester', $academicTerm->term);
        $this->assertSame('2028-01-10', $academicTerm->start_date->toDateString());
        $this->assertSame('2028-05-19', $academicTerm->end_date->toDateString());
        $this->assertSame('inactive', $academicTerm->status);
    }

    public function test_update_resending_its_own_academic_year_and_term_returns_200(): void
    {
        $this->actingAsAdministrator();
        $academicTerm = AcademicTerm::factory()->create(['academic_year' => '2026-2027', 'term' => 'First Semester']);

        $response = $this->putJson("/api/v1/academic-terms/{$academicTerm->id}", $this->validPayload(['status' => 'inactive']));

        $response->assertOk()->assertJsonPath('data.status', 'inactive');
    }

    public function test_update_with_another_terms_academic_year_and_term_returns_422(): void
    {
        $this->actingAsAdministrator();
        AcademicTerm::factory()->create(['academic_year' => '2026-2027', 'term' => 'First Semester']);
        $academicTerm = AcademicTerm::factory()->create(['academic_year' => '2027-2028', 'term' => 'First Semester']);

        $response = $this->putJson("/api/v1/academic-terms/{$academicTerm->id}", $this->validPayload());

        $this->assertValidationFailed($response, ['term']);
        $this->assertDatabaseHas('academic_terms', ['id' => $academicTerm->id, 'academic_year' => '2027-2028']);
    }

    public function test_update_moving_only_the_academic_year_into_an_existing_pair_returns_422(): void
    {
        $this->actingAsAdministrator();
        AcademicTerm::factory()->create(['academic_year' => '2026-2027', 'term' => 'First Semester']);
        $academicTerm = AcademicTerm::factory()->create(['academic_year' => '2027-2028', 'term' => 'First Semester']);

        $response = $this->putJson("/api/v1/academic-terms/{$academicTerm->id}", ['academic_year' => '2026-2027']);

        $this->assertValidationFailed($response, ['academic_year']);
        $this->assertDatabaseHas('academic_terms', ['id' => $academicTerm->id, 'academic_year' => '2027-2028']);
    }

    public function test_update_with_an_end_date_before_the_start_date_returns_422(): void
    {
        $this->actingAsAdministrator();
        $academicTerm = AcademicTerm::factory()->create();

        $response = $this->putJson("/api/v1/academic-terms/{$academicTerm->id}", [
            'start_date' => '2026-12-18',
            'end_date' => '2026-08-03',
        ]);

        $this->assertValidationFailed($response, ['end_date']);
    }

    public function test_update_with_only_an_end_date_before_the_stored_start_date_returns_422(): void
    {
        $this->actingAsAdministrator();
        $academicTerm = AcademicTerm::factory()->create(['start_date' => '2026-08-03', 'end_date' => '2026-12-18']);

        $response = $this->putJson("/api/v1/academic-terms/{$academicTerm->id}", ['end_date' => '2026-07-01']);

        $this->assertValidationFailed($response, ['end_date']);
        $this->assertSame('2026-12-18', $academicTerm->refresh()->end_date->toDateString());
    }

    public function test_update_with_only_a_start_date_after_the_stored_end_date_returns_422(): void
    {
        $this->actingAsAdministrator();
        $academicTerm = AcademicTerm::factory()->create(['start_date' => '2026-08-03', 'end_date' => '2026-12-18']);

        $response = $this->putJson("/api/v1/academic-terms/{$academicTerm->id}", ['start_date' => '2027-01-10']);

        $this->assertValidationFailed($response, ['start_date']);
        $this->assertSame('2026-08-03', $academicTerm->refresh()->start_date->toDateString());
    }

    public function test_update_with_only_a_status_returns_200_and_leaves_the_other_fields_unchanged(): void
    {
        $this->actingAsAdministrator();
        $academicTerm = AcademicTerm::factory()->create([
            'academic_year' => '2026-2027',
            'term' => 'First Semester',
            'start_date' => '2026-08-03',
            'end_date' => '2026-12-18',
            'status' => 'active',
        ]);

        $response = $this->putJson("/api/v1/academic-terms/{$academicTerm->id}", ['status' => 'inactive']);

        $response->assertOk()->assertJsonPath('data.status', 'inactive');
        $academicTerm->refresh();
        $this->assertSame('2026-2027', $academicTerm->academic_year);
        $this->assertSame('First Semester', $academicTerm->term);
        $this->assertSame('2026-08-03', $academicTerm->start_date->toDateString());
        $this->assertSame('2026-12-18', $academicTerm->end_date->toDateString());
    }

    public function test_destroy_returns_204_and_deletes_the_academic_term(): void
    {
        $this->actingAsAdministrator();
        $academicTerm = AcademicTerm::factory()->create();

        $response = $this->deleteJson("/api/v1/academic-terms/{$academicTerm->id}");

        $response->assertNoContent();
        $this->assertModelMissing($academicTerm);
    }

    public function test_destroy_returns_409_when_the_academic_term_has_course_offerings(): void
    {
        $this->actingAsAdministrator();
        $academicTerm = AcademicTerm::factory()->create();
        CourseOffering::factory()->create(['academic_term_id' => $academicTerm->id]);

        $response = $this->deleteJson("/api/v1/academic-terms/{$academicTerm->id}");

        $response->assertConflict();
        $this->assertModelExists($academicTerm);
    }

    private function actingAsAdministrator(): void
    {
        Sanctum::actingAs(User::factory()->administrator()->create());
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'academic_year' => '2026-2027',
            'term' => 'First Semester',
            'start_date' => '2026-08-03',
            'end_date' => '2026-12-18',
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
