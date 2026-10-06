<?php

namespace Tests\Feature;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_number_must_be_unique_in_database(): void
    {
        Student::factory()->create([
            'student_number' => '123456',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        Student::factory()->create([
            'student_number' => '123456',
        ]);
    }

    public function test_duplicate_enrollment_is_prevented_by_database_constraint(): void
    {
        $student = Student::factory()->create();
        $offering = CourseOffering::factory()->create();

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
        ]);
    }
}
