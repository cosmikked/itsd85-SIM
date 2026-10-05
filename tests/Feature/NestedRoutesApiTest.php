<?php

namespace Tests\Feature;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NestedRoutesApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdministrator(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'administrator']));
    }

    public function test_can_get_student_enrollments(): void
    {
        $this->actingAsAdministrator();
        $student = Student::factory()->create();
        $enrollment = Enrollment::factory()->create(['student_id' => $student->id]);

        $response = $this->getJson("/api/v1/students/{$student->id}/enrollments");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $enrollment->id);
    }

    public function test_can_get_student_grades(): void
    {
        $this->actingAsAdministrator();
        $student = Student::factory()->create();
        $enrollment = Enrollment::factory()->create(['student_id' => $student->id]);
        $grade = Grade::factory()->create(['enrollment_id' => $enrollment->id, 'remarks' => 'Passed']);

        $response = $this->getJson("/api/v1/students/{$student->id}/grades");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $grade->id)
            ->assertJsonPath('data.0.remarks', 'Passed');
    }

    public function test_can_get_course_offering_students(): void
    {
        $this->actingAsAdministrator();
        $offering = CourseOffering::factory()->create();
        $student = Student::factory()->create();
        $enrollment = Enrollment::factory()->create(['student_id' => $student->id, 'course_offering_id' => $offering->id]);

        $response = $this->getJson("/api/v1/course-offerings/{$offering->id}/students");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $student->id);
    }
}
