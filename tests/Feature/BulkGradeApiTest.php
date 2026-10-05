<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\GradeScaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BulkGradeApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdministrator(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'administrator']));
    }

    public function test_bulk_upload_is_transactional(): void
    {
        $this->seed(GradeScaleSeeder::class);
        $this->actingAsAdministrator();

        $term = AcademicTerm::factory()->create([
            'midterm_grading_deadline' => now()->addDays(5),
            'final_grading_deadline' => now()->addDays(10),
        ]);

        $offering = CourseOffering::factory()->create(['academic_term_id' => $term->id]);

        $enrollment1 = Enrollment::factory()->create([
            'course_offering_id' => $offering->id,
            'status' => 'enrolled',
        ]);
        $enrollment2 = Enrollment::factory()->create([
            'course_offering_id' => $offering->id,
            'status' => 'enrolled',
        ]);

        $response = $this->putJson("/api/v1/course-offerings/{$offering->id}/grades", [
            'grades' => [
                [
                    'enrollment_id' => $enrollment1->id,
                    'midterm_raw_score' => 85, // valid
                ],
                [
                    'enrollment_id' => $enrollment2->id,
                    'midterm_raw_score' => 105, // invalid (out of bounds)
                ],
            ],
        ]);

        $response->assertStatus(422);

        // Assert database is unchanged (neither grade should be saved because of transaction)
        $this->assertDatabaseMissing('grades', [
            'enrollment_id' => $enrollment1->id,
            'midterm_raw_score' => 85,
        ]);
    }
}
