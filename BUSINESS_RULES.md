# Student Information Management - Business Rules

This document outlines the core business logic and rules implemented in the SIM REST API. It is organized by domain and links to the specific files where the rules are enforced.

## 1. Academic Terms
* **Consecutive Academic Years:** The academic year must be formatted as `YYYY-YYYY` with strictly consecutive years (e.g., `2024-2025`).
  * Enforced in: `app/Rules/ConsecutiveAcademicYear.php`, `app/Http/Requests/StoreAcademicTermRequest.php`, `app/Http/Requests/UpdateAcademicTermRequest.php`
* **Logical Term Dates:** The academic term's end date must be strictly after the start date.
  * Enforced in: `app/Http/Requests/StoreAcademicTermRequest.php`, `app/Http/Requests/UpdateAcademicTermRequest.php`
* **Term Uniqueness:** The combination of an academic year and term (e.g., `2024-2025 First Semester`) must be unique across the system.
  * Enforced in: `app/Http/Requests/StoreAcademicTermRequest.php`, `app/Http/Requests/UpdateAcademicTermRequest.php`

## 2. Students & Programs
* **Valid Birth Dates:** A student's birth date cannot be in the future.
  * Enforced in: `app/Http/Requests/StoreStudentRequest.php`, `app/Http/Requests/UpdateStudentRequest.php`
* **Year Level Limits:** A student's year level must be between 1 and 4.
  * Enforced in: `app/Http/Requests/StoreStudentRequest.php`, `app/Http/Requests/UpdateStudentRequest.php`
* **Contact Uniqueness:** A student's contact number and email address must be unique across all students.
  * Enforced in: `app/Http/Requests/StoreStudentRequest.php`, `app/Http/Requests/UpdateStudentRequest.php`

## 3. Course Offerings
* **Capacity Reduction Limits:** An administrator cannot reduce a course offering's capacity below the current number of active (`enrolled`) students.
  * Enforced in: `app/Http/Requests/UpdateCourseOfferingRequest.php`
* **Section Uniqueness:** A section identifier (e.g., "A1") must be unique for a specific course within a specific academic term.
  * Enforced in: `app/Http/Requests/StoreCourseOfferingRequest.php`, `app/Http/Requests/UpdateCourseOfferingRequest.php`

## 4. Enrollments
* **Enrollment Date Validity:** A student's enrollment date must fall on or after the start date of the associated academic term.
  * Enforced in: `app/Http/Requests/StoreEnrollmentRequest.php`, `app/Http/Requests/UpdateEnrollmentRequest.php`
* **Duplicate Prevention:** A student cannot be enrolled in the exact same course offering more than once.
  * Enforced in: `app/Http/Requests/StoreEnrollmentRequest.php`
* **Class Capacity Enforcement:** A course offering cannot exceed its defined capacity. Only active (`enrolled`) students consume a seat; students with `dropped` or `completed` statuses do not count against the limit.
  * Enforced in: `app/Http/Requests/StoreEnrollmentRequest.php`, `app/Http/Requests/UpdateEnrollmentRequest.php`
* **Open Enrollment Requirement:** Students can only be newly enrolled in a course offering if its status is currently `open`.
  * Enforced in: `app/Http/Requests/StoreEnrollmentRequest.php`
* **Meaning of Deletion vs. Dropping:** Hard-deleting an enrollment is strictly reserved for fixing clerical errors. Dropping a class is represented by changing the enrollment status to `dropped`.
  * Enforced in: `app/Http/Controllers/EnrollmentController.php`

## 5. Deletion Protections (Data Integrity)
To maintain referential integrity and historical records, the system prevents the deletion of records that have dependencies. Instead of cascading deletes, it aborts with a `409 Conflict`:
* **Programs** cannot be deleted if they have enrolled students. (`app/Http/Controllers/ProgramController.php`)
* **Courses** cannot be deleted if they are used in course offerings. (`app/Http/Controllers/CourseController.php`)
* **Academic Terms** cannot be deleted if they have scheduled course offerings. (`app/Http/Controllers/AcademicTermController.php`)
* **Students** cannot be deleted if they have any enrollment history. (`app/Http/Controllers/StudentController.php`)
* **Course Offerings** cannot be deleted if any students have ever enrolled in them. (`app/Http/Controllers/CourseOfferingController.php`)
* **Enrollments** cannot be hard-deleted if their status is `completed`, protecting finalized academic records. (`app/Http/Controllers/EnrollmentController.php`)
