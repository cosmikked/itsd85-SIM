# SIM Frontend Business Rules & Data Constraints

This document consolidates all the core business processes, rules, and data constraints enforced by the SIM REST API backend. It serves as the single source of truth for frontend development (e.g., using Claude Code) to ensure the UI properly reflects validation rules, handles authorization, and manages data relationships accurately.

## 1. Academic Terms
* **Consecutive Academic Years:** The academic year must be formatted as `YYYY-YYYY` with strictly consecutive years (e.g., `2024-2025`).
* **Logical Term Dates:** The academic term's end date must be strictly after the start date.
* **Term Uniqueness:** The combination of an academic year and term (e.g., `2024-2025 First Semester`) must be unique across the system.
* **Grading Deadlines:** The system tracks three distinct deadlines per term to enforce strict grading windows. The frontend should display these to users to avoid `403 Forbidden` errors:
  * `midterm_grading_deadline` (datetime)
  * `final_grading_deadline` (datetime)
  * `inc_completion_deadline` (date)

## 2. Students & Programs
* **Valid Birth Dates:** A student's birth date cannot be in the future.
* **Year Level Limits:** A student's year level must be between 1 and 6 (for programs with 6 years).
* **Contact Uniqueness:** A student's contact number and email address must be unique across all students.

## 3. Course Offerings & Schedules
* **Active Term Requirement:** New course offerings can only be created within an 'active' academic term.
* **Capacity Reduction Limits:** An administrator cannot reduce a course offering's capacity below the current number of active (`enrolled`) students.
* **Section Uniqueness:** A section identifier (e.g., "A1") must be unique for a specific course within a specific academic term.
* **Multiple Schedules Support:** A course offering can have multiple schedules (e.g., Lecture and Lab sessions). Each schedule requires a `room_id`, `day_of_week`, `start_time`, and `end_time`.
* **Schedule Conflicts Prevention:** The backend actively prevents schedule conflicts. The frontend should validate or at least gracefully handle these errors:
  * **Internal Payload Conflict:** A single submission cannot contain overlapping times on the same day for the same room, nor overlapping times for the same instructor.
  * **Room Conflict:** A specific room cannot be booked for overlapping times on the same day within the same academic term.
  * **Instructor Conflict:** An instructor cannot be scheduled to teach overlapping classes on the same day within the same academic term.
* **Valid Time Frames:** Each schedule's `end_time` must be strictly after its `start_time`.

## 4. Enrollments
* **Enrollment Date Validity:** A student's enrollment date must fall on or after the start date of the associated academic term.
* **Duplicate Prevention:** A student cannot be enrolled in the exact same course offering more than once.
* **Class Capacity Enforcement:** A course offering cannot exceed its defined capacity. Only active (`enrolled`) students consume a seat; students with `dropped` or `completed` statuses do not count against the limit.
* **Open Enrollment Requirement:** Students can only be newly enrolled in a course offering if its status is currently `open`.
* **Deletion vs. Dropping:** Hard-deleting an enrollment is strictly reserved for fixing clerical errors. Standard dropping or withdrawing from a class is represented by changing the enrollment status to `dropped`.

## 5. Deletion Protections (Data Integrity)
To maintain referential integrity and historical records, the system prevents the deletion of records that have dependencies. It aborts with a `409 Conflict` (no cascading deletes):
* **Programs** cannot be deleted if they have enrolled students. 
* **Courses** cannot be deleted if they are used in course offerings.
* **Academic Terms** cannot be deleted if they have scheduled course offerings.
* **Students** cannot be deleted if they have any enrollment history.
* **Course Offerings** cannot be deleted if any students have ever enrolled in them.
* **Rooms** cannot be deleted if they are used in any course offering schedule.
* **Enrollments** cannot be hard-deleted if their status is `completed`, protecting finalized academic records.

## 6. Grades & Academic Records
* **Draft / Published Grades:** Every grade has `midterm_status` and `final_status` (`draft` | `published`). Instructors save drafts (`POST /grades`, `PATCH /grades/{id}`, `PUT /course-offerings/{id}/grades`) and then publish a period with `POST /grades/{id}/publish` or `POST /course-offerings/{id}/grades/publish` (body `{"period": "midterm" | "final"}`; the bulk call returns `data: {period, published, skipped}`). A published period is read-only for the instructor: trying to change it returns `403` (resending the same value is fine). Show a "Draft" / "Published" badge to instructors and disable the inputs of published periods. Students only ever receive published data: unpublished fields are simply absent from the JSON (e.g. no `remarks` until the final is published), and a grade with nothing published returns `403` on `GET /grades/{id}` and is left out of lists.
* **Scope & Authorization:** Only the assigned instructor (`instructor_id` on the `CourseOffering`) and system Admins can update grades. Bulk uploads are transactional (if one grade fails validation, the entire request is rejected with `422` and no grades are saved).
* **Enrollment Status Requirement:** Grades can only be submitted for enrollments with an `enrolled` or `active` status.
* **Automatic Dropping Handling:** When an enrollment drops/withdraws, the system automatically clears raw scores:
  * **Before Midterms:** Remark becomes "W" (Withdrawn).
  * **After Midterms:** Remark is "W" if passing (`midterm_equivalent_grade` <= 3.0), or "5.0" (Failed) if failing.
* **Grade Calculation & Submission:** Instructors submit raw scores. The backend maps them to equivalent grades using the `GradeScale` table. Final raw scores are automatically calculated as `(1/3 × midterm) + (2/3 × finalterm)`. The frontend should only send raw scores, not equivalent grades.
* **Strict Grading Windows:** Instructors can only submit or update grades before the deadline defined on the associated Academic Term (`midterm_grading_deadline`, `final_grading_deadline`).
* **Re-examination (Removal Exam):** A student is eligible for a re-exam only if their `final_equivalent_grade` is exactly 4.0. If the re-exam raw score >= 50, the equivalent grade becomes 3.0; otherwise, it drops to 5.0.
* **Incomplete Grades (INC) Lifecycle:** Instructors submit a boolean `is_inc` flag. If true, the grade adopts the term's `inc_completion_deadline`. If the student finishes requirements, the instructor submits the actual score. If the deadline passes, an automated daily job flips the grade to 5.0 (Failed).
* **Automatic Remarks:** The backend fully manages the `remarks` column. Instructors do not manually input remarks. The frontend should display these as read-only fields:
  * `1.0` - `3.0` -> 'Passed'
  * `4.0` -> 'Conditional'
  * `5.0` -> 'Failed'
  * `is_inc=true` -> 'Incomplete'
  * Dropped logic -> 'Withdrawn' or 'Failed'
