<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CourseOfferingApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function protectedRoutes(): array
    {
        return [
            'index' => ['getJson', '/api/v1/course-offerings'],
            'show' => ['getJson', '/api/v1/course-offerings/{offering}'],
            'store' => ['postJson', '/api/v1/course-offerings'],
            'update' => ['putJson', '/api/v1/course-offerings/{offering}'],
            'destroy' => ['deleteJson', '/api/v1/course-offerings/{offering}'],
        ];
    }

    #[DataProvider('protectedRoutes')]
    public function test_returns_401_without_a_token_on_every_course_offering_route(string $method, string $uri): void
    {
        $offering = CourseOffering::factory()->create();

        $response = $this->{$method}(str_replace('{offering}', (string) $offering->id, $uri));

        $response->assertUnauthorized();
    }

    public function test_index_returns_200_with_the_course_offerings(): void
    {
        $this->actingAsAdministrator();
        CourseOffering::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/course-offerings');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Course Offerings retrieved successfully.')
            ->assertJsonCount(3, 'data');
    }

    public function test_index_returns_15_course_offerings_per_page(): void
    {
        $this->actingAsAdministrator();
        $term = AcademicTerm::factory()->create();
        CourseOffering::factory()->count(16)->recycle($term)->create();

        $response = $this->getJson('/api/v1/course-offerings');

        $response->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 16);
    }

    public function test_index_accepts_per_page_parameter(): void
    {
        $this->actingAsAdministrator();
        $term = AcademicTerm::factory()->create();
        CourseOffering::factory()->count(16)->recycle($term)->create();

        $response = $this->getJson('/api/v1/course-offerings?per_page=5');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 16);
    }

    public function test_index_can_filter_by_exact_matches(): void
    {
        $this->actingAsAdministrator();
        $term = AcademicTerm::factory()->create();
        $course = Course::factory()->create();
        CourseOffering::factory()->create(['status' => 'open', 'academic_term_id' => $term->id, 'course_id' => $course->id, 'section' => 'A']);
        CourseOffering::factory()->create(['status' => 'closed', 'academic_term_id' => $term->id, 'course_id' => $course->id, 'section' => 'B']);

        $response = $this->getJson('/api/v1/course-offerings?status=open&academic_term_id='.$term->id.'&course_id='.$course->id);

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_show_returns_200_with_the_course_offering(): void
    {
        $this->actingAsAdministrator();
        $offering = CourseOffering::factory()->create(['section' => 'A1']);

        $response = $this->getJson("/api/v1/course-offerings/{$offering->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Course Offering retrieved successfully.')
            ->assertJsonPath('data.id', $offering->id)
            ->assertJsonPath('data.section', 'A1');
    }

    public function test_store_with_valid_data_returns_201_and_creates_the_course_offering(): void
    {
        $this->actingAsAdministrator();

        $course = Course::factory()->create();
        $term = AcademicTerm::factory()->create(['status' => 'active']);
        $instructor = User::factory()->create(); // Assuming an instructor role is handled or ignored for now

        $response = $this->postJson('/api/v1/course-offerings', [
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'instructor_id' => $instructor->id,
            'section' => 'A1',
            'schedule' => 'MWF 8:00AM - 9:00AM',
            'room' => 'Room 101',
            'capacity' => 40,
            'status' => 'open',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.section', 'A1')
            ->assertJsonPath('data.capacity', 40);

        $this->assertDatabaseHas('course_offerings', [
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'section' => 'A1',
        ]);
    }

    public function test_store_with_a_duplicate_section_for_same_term_and_course_returns_422(): void
    {
        $this->actingAsAdministrator();

        $course = Course::factory()->create();
        $term = AcademicTerm::factory()->create(['status' => 'active']);

        CourseOffering::factory()->create([
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'section' => 'A1',
        ]);

        $response = $this->postJson('/api/v1/course-offerings', [
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'instructor_id' => User::factory()->create()->id,
            'section' => 'A1',
            'schedule' => 'TTH 8:00AM - 9:30AM',
            'capacity' => 40,
        ]);

        $this->assertValidationFailed($response, ['section']);
    }

    public function test_store_with_an_inactive_academic_term_returns_422(): void
    {
        $this->actingAsAdministrator();

        $course = Course::factory()->create();
        $term = AcademicTerm::factory()->create(['status' => 'inactive']);

        $response = $this->postJson('/api/v1/course-offerings', [
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'instructor_id' => User::factory()->create()->id,
            'section' => 'A1',
            'schedule' => 'MWF 8:00AM - 9:00AM',
            'capacity' => 40,
        ]);

        $this->assertValidationFailed($response, ['academic_term_id']);
    }

    public function test_update_with_valid_data_returns_200_and_updates_the_course_offering(): void
    {
        $this->actingAsAdministrator();
        $offering = CourseOffering::factory()->create(['capacity' => 30]);

        $response = $this->putJson("/api/v1/course-offerings/{$offering->id}", array_merge($offering->toArray(), [
            'capacity' => 50,
            'room' => 'New Room',
        ]));

        $response->assertOk()
            ->assertJsonPath('data.capacity', 50)
            ->assertJsonPath('data.room', 'New Room');

        $this->assertDatabaseHas('course_offerings', [
            'id' => $offering->id,
            'capacity' => 50,
        ]);
    }

    public function test_update_resending_its_own_unique_fields_returns_200(): void
    {
        $this->actingAsAdministrator();
        $offering = CourseOffering::factory()->create(['section' => 'A1', 'capacity' => 40]);

        // Resend the exact same unique fields, but change capacity
        $response = $this->putJson("/api/v1/course-offerings/{$offering->id}", [
            'instructor_id' => $offering->instructor_id,
            'section' => 'A1', // Same section
            'schedule' => $offering->schedule,
            'capacity' => 45,
        ]);

        $response->assertOk()->assertJsonPath('data.capacity', 45);
    }

    public function test_update_rejects_changes_to_course_id_or_academic_term_id(): void
    {
        $this->actingAsAdministrator();
        $offering = CourseOffering::factory()->create();

        $newCourse = Course::factory()->create();
        $newTerm = AcademicTerm::factory()->create();

        $response = $this->putJson("/api/v1/course-offerings/{$offering->id}", [
            'course_id' => $newCourse->id,
            'academic_term_id' => $newTerm->id,
            'capacity' => 50,
        ]);

        $response->assertOk();

        // Assert that capacity changed, but course and term did NOT change
        $this->assertDatabaseHas('course_offerings', [
            'id' => $offering->id,
            'capacity' => 50,
            'course_id' => $offering->course_id,
            'academic_term_id' => $offering->academic_term_id,
        ]);
    }

    public function test_update_capacity_below_current_enrollment_returns_422(): void
    {
        $this->actingAsAdministrator();
        $offering = CourseOffering::factory()->create(['capacity' => 40]);

        $program = Program::factory()->create();
        // Since we refactored capacity checking to only count 'enrolled' status, explicitly set it
        Enrollment::factory()->count(35)->recycle($program)->create([
            'course_offering_id' => $offering->id,
            'status' => 'enrolled',
        ]);

        $response = $this->putJson("/api/v1/course-offerings/{$offering->id}", array_merge($offering->toArray(), [
            'capacity' => 30, // Less than the 35 enrolled students
        ]));

        $this->assertValidationFailed($response, ['capacity']);
    }

    public function test_update_course_offering_capacity_ignores_dropped_students(): void
    {
        $this->actingAsAdministrator();
        $offering = CourseOffering::factory()->create(['capacity' => 40]);
        $program = Program::factory()->create();

        // 35 dropped, 5 enrolled
        Enrollment::factory()->count(35)->recycle($program)->create([
            'course_offering_id' => $offering->id,
            'status' => 'dropped',
        ]);
        Enrollment::factory()->count(5)->recycle($program)->create([
            'course_offering_id' => $offering->id,
            'status' => 'enrolled',
        ]);

        // Attempt to lower capacity to 10. (Should pass because only 5 are actually 'enrolled')
        $response = $this->putJson("/api/v1/course-offerings/{$offering->id}", array_merge($offering->toArray(), [
            'capacity' => 10,
        ]));

        $response->assertOk();
    }

    public function test_destroy_returns_204_and_deletes_the_course_offering(): void
    {
        $this->actingAsAdministrator();
        $offering = CourseOffering::factory()->create();

        $response = $this->deleteJson("/api/v1/course-offerings/{$offering->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted($offering);
    }

    public function test_destroy_returns_409_when_the_offering_has_enrollments(): void
    {
        $this->actingAsAdministrator();
        $offering = CourseOffering::factory()->create();
        Enrollment::factory()->create(['course_offering_id' => $offering->id]);

        $response = $this->deleteJson("/api/v1/course-offerings/{$offering->id}");

        $response->assertConflict();
        $this->assertModelExists($offering);
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
