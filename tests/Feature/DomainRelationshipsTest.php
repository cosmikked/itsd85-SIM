<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_has_many_students(): void
    {
        $program = Program::factory()->create();
        $student = Student::factory()->create(['program_id' => $program->id]);

        $this->assertTrue($program->students->contains($student));
        $this->assertTrue($student->program->is($program));
    }

    public function test_course_has_many_course_offerings(): void
    {
        $course = Course::factory()->create();
        $offering = CourseOffering::factory()->create(['course_id' => $course->id]);

        $this->assertTrue($course->courseOfferings->contains($offering));
        $this->assertTrue($offering->course->is($course));
    }

    public function test_academic_term_has_many_course_offerings(): void
    {
        $term = AcademicTerm::factory()->create();
        $offering = CourseOffering::factory()->create(['academic_term_id' => $term->id]);

        $this->assertTrue($term->courseOfferings->contains($offering));
        $this->assertTrue($offering->academicTerm->is($term));
    }

    public function test_instructor_has_many_course_offerings(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $offering = CourseOffering::factory()->create(['instructor_id' => $instructor->id]);

        $this->assertTrue($instructor->courseOfferings->contains($offering));
        $this->assertTrue($offering->instructor->is($instructor));
    }

    public function test_student_has_many_enrollments(): void
    {
        $student = Student::factory()->create();
        $enrollment = Enrollment::factory()->create(['student_id' => $student->id]);

        $this->assertTrue($student->enrollments->contains($enrollment));
        $this->assertTrue($enrollment->student->is($student));
    }

    public function test_course_offering_has_many_enrollments(): void
    {
        $offering = CourseOffering::factory()->create();
        $enrollment = Enrollment::factory()->create(['course_offering_id' => $offering->id]);

        $this->assertTrue($offering->enrollments->contains($enrollment));
        $this->assertTrue($enrollment->courseOffering->is($offering));
    }

    public function test_enrollment_has_one_grade(): void
    {
        $enrollment = Enrollment::factory()->create();
        $grade = Grade::factory()->create(['enrollment_id' => $enrollment->id]);

        $this->assertTrue($enrollment->grade->is($grade));
        $this->assertTrue($grade->enrollment->is($enrollment));
    }
}
