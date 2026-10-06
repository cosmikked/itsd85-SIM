<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CourseApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function protectedRoutes(): array
    {
        return [
            'index' => ['getJson', '/api/v1/courses'],
            'show' => ['getJson', '/api/v1/courses/{course}'],
            'store' => ['postJson', '/api/v1/courses'],
            'update' => ['putJson', '/api/v1/courses/{course}'],
            'destroy' => ['deleteJson', '/api/v1/courses/{course}'],
        ];
    }

    /**
     * @return array<string, array{int|float|string}>
     */
    public static function invalidUnits(): array
    {
        return [
            'zero' => [0],
            'above the maximum' => [10],
            'negative' => [-1],
            'not a number' => ['three'],
            'decimal' => [2.5],
        ];
    }

    /**
     * @return array<string, array{int}>
     */
    public static function boundaryUnits(): array
    {
        return [
            'minimum' => [1],
            'maximum' => [9],
        ];
    }

    #[DataProvider('protectedRoutes')]
    public function test_returns_401_without_a_token_on_every_course_route(string $method, string $uri): void
    {
        $course = Course::factory()->create();

        $response = $this->{$method}(str_replace('{course}', (string) $course->id, $uri));

        $response->assertUnauthorized();
    }

    public function test_index_returns_200_with_the_courses(): void
    {
        $this->actingAsAdministrator();
        Course::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/courses');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Courses retrieved successfully.')
            ->assertJsonCount(3, 'data');
    }

    public function test_index_returns_15_courses_per_page(): void
    {
        $this->actingAsAdministrator();
        Course::factory()->count(16)->create();

        $response = $this->getJson('/api/v1/courses');

        $response->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 16);
    }

    public function test_index_accepts_per_page_parameter(): void
    {
        $this->actingAsAdministrator();
        Course::factory()->count(16)->create();

        $response = $this->getJson('/api/v1/courses?per_page=5');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 16);
    }

    public function test_index_can_search_by_title_or_code(): void
    {
        $this->actingAsAdministrator();
        Course::factory()->create(['course_code' => 'TARGET101', 'course_title' => 'Math']);
        Course::factory()->create(['course_code' => 'ENG101', 'course_title' => 'Target English']);
        Course::factory()->create(['course_code' => 'SCI101', 'course_title' => 'Science']);

        $response = $this->getJson('/api/v1/courses?search=target');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_index_can_filter_by_status(): void
    {
        $this->actingAsAdministrator();
        Course::factory()->create(['status' => 'active']);
        Course::factory()->create(['status' => 'inactive']);

        $response = $this->getJson('/api/v1/courses?status=active');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_show_returns_200_with_the_course(): void
    {
        $this->actingAsAdministrator();
        $course = Course::factory()->create([
            'course_code' => 'CS101',
            'course_title' => 'Introduction to Programming',
            'units' => 3,
        ]);

        $response = $this->getJson("/api/v1/courses/{$course->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Course retrieved successfully.')
            ->assertJsonPath('data.id', $course->id)
            ->assertJsonPath('data.course_code', 'CS101')
            ->assertJsonPath('data.course_title', 'Introduction to Programming')
            ->assertJsonPath('data.units', 3);
    }

    public function test_show_returns_404_for_an_unknown_course(): void
    {
        $this->actingAsAdministrator();

        $response = $this->getJson('/api/v1/courses/999');

        $response->assertNotFound();
    }

    public function test_store_with_valid_data_returns_201_and_creates_the_course(): void
    {
        $this->actingAsAdministrator();

        $response = $this->postJson('/api/v1/courses', [
            'course_code' => 'CS101',
            'course_title' => 'Introduction to Programming',
            'description' => 'Fundamentals of programming logic.',
            'units' => 3,
            'status' => 'inactive',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Course created successfully.')
            ->assertJsonPath('data.course_code', 'CS101')
            ->assertJsonPath('data.units', 3)
            ->assertJsonPath('data.status', 'inactive');
        $this->assertDatabaseHas('courses', [
            'course_code' => 'CS101',
            'course_title' => 'Introduction to Programming',
            'units' => 3,
            'status' => 'inactive',
        ]);
    }

    public function test_store_with_an_empty_payload_returns_422_for_the_required_fields(): void
    {
        $this->actingAsAdministrator();

        $response = $this->postJson('/api/v1/courses', []);

        $this->assertValidationFailed($response, ['course_code', 'course_title', 'units']);
        $this->assertDatabaseCount('courses', 0);
    }

    public function test_store_with_a_duplicate_course_code_returns_422(): void
    {
        $this->actingAsAdministrator();
        Course::factory()->create(['course_code' => 'CS101']);

        $response = $this->postJson('/api/v1/courses', [
            'course_code' => 'CS101',
            'course_title' => 'Another Course',
            'units' => 3,
        ]);

        $this->assertValidationFailed($response, ['course_code']);
        $this->assertDatabaseCount('courses', 1);
    }

    public function test_store_with_an_invalid_status_returns_422(): void
    {
        $this->actingAsAdministrator();

        $response = $this->postJson('/api/v1/courses', [
            'course_code' => 'CS101',
            'course_title' => 'Introduction to Programming',
            'units' => 3,
            'status' => 'archived',
        ]);

        $this->assertValidationFailed($response, ['status']);
        $this->assertDatabaseCount('courses', 0);
    }

    public function test_store_without_a_description_returns_201(): void
    {
        $this->actingAsAdministrator();

        $response = $this->postJson('/api/v1/courses', [
            'course_code' => 'CS101',
            'course_title' => 'Introduction to Programming',
            'units' => 3,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('courses', ['course_code' => 'CS101', 'description' => null]);
    }

    #[DataProvider('invalidUnits')]
    public function test_store_rejects_units_outside_the_allowed_range(int|float|string $units): void
    {
        $this->actingAsAdministrator();

        $response = $this->postJson('/api/v1/courses', [
            'course_code' => 'CS101',
            'course_title' => 'Introduction to Programming',
            'units' => $units,
        ]);

        $this->assertValidationFailed($response, ['units']);
        $this->assertDatabaseCount('courses', 0);
    }

    #[DataProvider('boundaryUnits')]
    public function test_store_accepts_units_at_the_range_boundaries(int $units): void
    {
        $this->actingAsAdministrator();

        $response = $this->postJson('/api/v1/courses', [
            'course_code' => 'CS101',
            'course_title' => 'Introduction to Programming',
            'units' => $units,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('courses', ['course_code' => 'CS101', 'units' => $units]);
    }

    public function test_update_with_valid_data_returns_200_and_updates_the_course(): void
    {
        $this->actingAsAdministrator();
        $course = Course::factory()->create([
            'course_code' => 'CS101',
            'course_title' => 'Old Title',
            'units' => 3,
        ]);

        $response = $this->putJson("/api/v1/courses/{$course->id}", [
            'course_code' => 'CS102',
            'course_title' => 'New Title',
            'units' => 4,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Course updated successfully.')
            ->assertJsonPath('data.course_code', 'CS102')
            ->assertJsonPath('data.course_title', 'New Title')
            ->assertJsonPath('data.units', 4);
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'course_code' => 'CS102',
            'course_title' => 'New Title',
            'units' => 4,
        ]);
    }

    public function test_update_resending_its_own_course_code_returns_200(): void
    {
        $this->actingAsAdministrator();
        $course = Course::factory()->create(['course_code' => 'CS101']);

        $response = $this->putJson("/api/v1/courses/{$course->id}", [
            'course_code' => 'CS101',
            'course_title' => 'Renamed Course',
        ]);

        $response->assertOk()->assertJsonPath('data.course_title', 'Renamed Course');
    }

    public function test_update_with_another_courses_course_code_returns_422(): void
    {
        $this->actingAsAdministrator();
        Course::factory()->create(['course_code' => 'CS101']);
        $course = Course::factory()->create(['course_code' => 'CS201']);

        $response = $this->putJson("/api/v1/courses/{$course->id}", [
            'course_code' => 'CS101',
            'course_title' => 'Renamed Course',
        ]);

        $this->assertValidationFailed($response, ['course_code']);
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'course_code' => 'CS201']);
    }

    public function test_update_with_units_outside_the_allowed_range_returns_422(): void
    {
        $this->actingAsAdministrator();
        $course = Course::factory()->create(['course_code' => 'CS101', 'units' => 3]);

        $response = $this->putJson("/api/v1/courses/{$course->id}", [
            'course_code' => 'CS101',
            'course_title' => 'Introduction to Programming',
            'units' => 10,
        ]);

        $this->assertValidationFailed($response, ['units']);
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'units' => 3]);
    }

    public function test_destroy_returns_204_and_deletes_the_course(): void
    {
        $this->actingAsAdministrator();
        $course = Course::factory()->create();

        $response = $this->deleteJson("/api/v1/courses/{$course->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted($course);
    }

    public function test_destroy_returns_409_when_the_course_has_course_offerings(): void
    {
        $this->actingAsAdministrator();
        $course = Course::factory()->create();
        CourseOffering::factory()->create(['course_id' => $course->id]);

        $response = $this->deleteJson("/api/v1/courses/{$course->id}");

        $response->assertConflict();
        $this->assertModelExists($course);
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

    public function test_index_can_sort_results_by_multiple_columns(): void
    {
        Sanctum::actingAs(User::factory()->administrator()->create());

        Course::query()->delete();
        Course::factory()->create(['units' => 3, 'course_title' => 'Apple', 'course_code' => 'A1']);
        Course::factory()->create(['units' => 3, 'course_title' => 'Banana', 'course_code' => 'B1']);
        Course::factory()->create(['units' => 4, 'course_title' => 'Zebra', 'course_code' => 'Z1']);

        $response = $this->getJson('/api/v1/courses?sort=-units,course_title');

        $response->assertStatus(200);

        $items = $response->json('data');
        $this->assertGreaterThanOrEqual(3, count($items));

        // Check order of the first 3 items
        $this->assertEquals('Zebra', (string) $items[0]['course_title']);
        $this->assertEquals('Apple', (string) $items[1]['course_title']);
        $this->assertEquals('Banana', (string) $items[2]['course_title']);
    }
}
