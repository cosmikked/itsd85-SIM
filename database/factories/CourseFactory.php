<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * Real courses spanning the subject areas covered by ProgramFactory's
     * degree programs, so generated data reads like an actual course catalog
     * instead of random sentences. Codes are all distinct up front, since
     * courses.course_code is unique in the database. There's no program_id
     * link in this schema — a course only connects to a program indirectly,
     * through course_offerings — so this alignment is thematic only.
     *
     * @var array<int, array{course_code: string, course_title: string, description: string, units: int}>
     */
    private const COURSES = [
        ['course_code' => 'GE101', 'course_title' => 'Mathematics in the Modern World', 'description' => 'Nature of mathematics and its applications in everyday life.', 'units' => 3],
        ['course_code' => 'GE102', 'course_title' => 'Purposive Communication', 'description' => 'Development of communication skills for academic and professional contexts.', 'units' => 3],
        ['course_code' => 'CS101', 'course_title' => 'Introduction to Programming', 'description' => 'Fundamentals of programming logic and problem solving using a high-level language.', 'units' => 3],
        ['course_code' => 'CS201', 'course_title' => 'Data Structures and Algorithms', 'description' => 'Covers common data structures, algorithm design, and complexity analysis.', 'units' => 3],
        ['course_code' => 'IT101', 'course_title' => 'Introduction to Information Technology', 'description' => 'Overview of hardware, software, and IT service management concepts.', 'units' => 3],
        ['course_code' => 'IT210', 'course_title' => 'Database Management Systems', 'description' => 'Relational database design, normalization, and SQL.', 'units' => 3],
        ['course_code' => 'IS150', 'course_title' => 'Systems Analysis and Design', 'description' => 'Techniques for analyzing business requirements and designing information systems.', 'units' => 3],
        ['course_code' => 'ACC101', 'course_title' => 'Financial Accounting', 'description' => 'Principles of recording, classifying, and summarizing financial transactions.', 'units' => 3],
        ['course_code' => 'BA101', 'course_title' => 'Principles of Management', 'description' => 'Fundamentals of planning, organizing, leading, and controlling in organizations.', 'units' => 3],
        ['course_code' => 'BA210', 'course_title' => 'Marketing Management', 'description' => 'Core concepts of market analysis, branding, and consumer behavior.', 'units' => 3],
        ['course_code' => 'NUR101', 'course_title' => 'Fundamentals of Nursing', 'description' => 'Basic nursing concepts, skills, and patient care principles.', 'units' => 5],
        ['course_code' => 'NUR210', 'course_title' => 'Anatomy and Physiology', 'description' => 'Structure and function of the human body systems.', 'units' => 4],
        ['course_code' => 'ED101', 'course_title' => 'Child and Adolescent Development', 'description' => 'Principles of human growth and development from childhood through adolescence.', 'units' => 3],
        ['course_code' => 'ED210', 'course_title' => 'Principles of Teaching', 'description' => 'Foundational theories and strategies in classroom instruction.', 'units' => 3],
        ['course_code' => 'CE101', 'course_title' => 'Engineering Mechanics', 'description' => 'Statics and dynamics of rigid bodies applied to civil structures.', 'units' => 3],
        ['course_code' => 'EE101', 'course_title' => 'Circuit Analysis', 'description' => 'DC and AC circuit theory and analysis techniques.', 'units' => 4],
        ['course_code' => 'PSY101', 'course_title' => 'General Psychology', 'description' => 'Introduction to the scientific study of behavior and mental processes.', 'units' => 3],
        ['course_code' => 'HM101', 'course_title' => 'Introduction to Hospitality Management', 'description' => 'Overview of the hotel, restaurant, and tourism industries.', 'units' => 3],
        ['course_code' => 'CS301', 'course_title' => 'Web Development', 'description' => 'Design and development of dynamic, client-server web applications.', 'units' => 3],
        ['course_code' => 'IT250', 'course_title' => 'Network Fundamentals', 'description' => 'Principles of computer networking, protocols, and network administration.', 'units' => 3],
        ['course_code' => 'ACC210', 'course_title' => 'Cost Accounting', 'description' => 'Cost behavior, allocation, and analysis for managerial decision-making.', 'units' => 3],
        ['course_code' => 'BA310', 'course_title' => 'Human Resource Management', 'description' => 'Principles of recruitment, training, and employee relations management.', 'units' => 3],
        ['course_code' => 'NUR310', 'course_title' => 'Community Health Nursing', 'description' => 'Nursing care principles applied to community and public health settings.', 'units' => 3],
        ['course_code' => 'HM210', 'course_title' => 'Food and Beverage Service', 'description' => 'Principles and practices of food and beverage service operations.', 'units' => 3],
        ['course_code' => 'ED310', 'course_title' => 'Assessment of Learning', 'description' => 'Principles and tools for evaluating student learning outcomes.', 'units' => 3],
        ['course_code' => 'PSY210', 'course_title' => 'Abnormal Psychology', 'description' => 'Study of atypical behavior patterns and psychological disorders.', 'units' => 3],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $course = fake()->unique()->randomElement(self::COURSES);

        return [
            'course_code' => $course['course_code'],
            'course_title' => $course['course_title'],
            'description' => $course['description'],
            'units' => $course['units'],
            'status' => fake()->randomElement(['active', 'inactive']),
        ];
    }
}
