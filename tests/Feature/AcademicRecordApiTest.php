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

class AcademicRecordApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdministrator(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'administrator']));
    }

    public function test_academic_record_is_grouped_by_term(): void
    {
        $this->actingAsAdministrator();
        $this->seed(GradeScaleSeeder::class);

        $student = Student::factory()->create();

        $term1 = AcademicTerm::factory()->create([
            'academic_year' => '2025-2026',
            'term' => 'First Semester',
        ]);
        $term2 = AcademicTerm::factory()->create([
            'academic_year' => '2025-2026',
            'term' => 'Second Semester',
        ]);

        $offering1 = CourseOffering::factory()->create(['academic_term_id' => $term1->id]);
        $offering2 = CourseOffering::factory()->create(['academic_term_id' => $term2->id]);

        $enrollment1 = Enrollment::factory()->create(['student_id' => $student->id, 'course_offering_id' => $offering1->id]);
        $enrollment2 = Enrollment::factory()->create(['student_id' => $student->id, 'course_offering_id' => $offering2->id]);

        Grade::factory()->create([
            'enrollment_id' => $enrollment1->id,
            'final_equivalent_grade' => 1.25,
            'remarks' => 'Passed',
        ]);

        Grade::factory()->create([
            'enrollment_id' => $enrollment2->id,
            'final_equivalent_grade' => 2.50,
            'remarks' => 'Passed',
        ]);

        $response = $this->getJson("/api/v1/students/{$student->id}/academic-record");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.academic_term', '2025-2026 First Semester')
            ->assertJsonPath('data.0.enrollments.0.final_equivalent_grade', 1.25)
            ->assertJsonPath('data.0.enrollments.0.remarks', 'Passed')
            ->assertJsonPath('data.1.academic_term', '2025-2026 Second Semester')
            ->assertJsonPath('data.1.enrollments.0.final_equivalent_grade', 2.50)
            ->assertJsonPath('data.1.enrollments.0.remarks', 'Passed');
    }
}
