# Grade Endpoints Business Rules & Implementation Plan

This document summarizes the agreed-upon business rules, logic, and implementation strategy for the Grading System.

## 1. Scope & Authorization
- **Endpoints**: Provide both single-enrollment and bulk (CourseOffering) grade update endpoints.
- **Authorization**: Only the assigned instructor (`instructor_id` on the `CourseOffering`) and system Admins can update grades.
- **Bulk Upload**: Bulk submissions are transactional. If one grade fails validation, the entire request is rejected (422) and no grades are saved.

## 2. Enrollment Status & Dropping
- **Active Only**: Grades can only be submitted for `Enrollment`s with an 'enrolled' or 'active' status.
- **Automatic Handling**: When an enrollment status changes to 'dropped' or 'withdrawn' (e.g., via an event/observer), the system automatically clears the raw scores.
  - If dropped *before* midterms: sets remark to "W" (Withdrawn).
  - If dropped *after* midterms: sets remark to "W" if passing (`midterm_equivalent_grade` <= 3.0), or "5.0" if failing.

## 3. Grade Calculation & Submission
- **Partial Updates**: Instructors can submit partial grades (e.g., only `midterm_raw_score`). The endpoint only processes the submitted terms.
- **Backend Calculation**: The client submits only the raw scores. The backend maps them to equivalent grades using the `GradeScale` table.
- **Final Grade Formula**: `final_raw_score` = (1/3 × `midterm_raw_score`) + (2/3 × `finalterm_raw_score`). The backend auto-calculates this and maps the `final_equivalent_grade` once both MT and FT scores are present.
- **Bounds Validation**: If a submitted raw score does not fall within any defined `GradeScale` min/max range, the system returns a 422 validation error.

## 4. Grading Deadlines (Academic Term)
- **Database Tracking**: The `academic_terms` table will have three new columns to manage strict grading windows:
  - `midterm_grading_deadline` (datetime)
  - `final_grading_deadline` (datetime)
  - `inc_completion_deadline` (date)
- **Enforcement**: The grading endpoints will check `now()` against these deadlines and return a `403 Forbidden` if the grading window has closed.

## 5. Re-examination (Removal Exam)
- **Eligibility**: Allowed only if the `final_equivalent_grade` is exactly 4.0, and both MT and FT grades exist.
- **Submission**: Instructors use the same grading endpoint to submit `re_exam_raw_score`.
- **Calculation Rule**: The normal `GradeScale` mapping is bypassed. If `re_exam_raw_score` >= 50, the `re_exam_equivalent_grade` becomes 3.0. If < 50, it becomes 5.0.

## 6. Incomplete Grades (INC) Lifecycle
- **Database Additions**: The `grades` table will have a new boolean `is_inc` column (default false) and a date `inc_expiration_date` column.
- **Submission**: The payload will include an `is_inc` boolean flag. If true, the system sets `is_inc = true` and copies the `inc_completion_deadline` from the active `AcademicTerm` into the `inc_expiration_date`.
- **Completion**: If the student completes the requirements before the deadline, the instructor submits the actual score. The system recalculates the grade, sets `is_inc = false`, and clears the expiration date.
- **Auto-Fail (Scheduled Job)**: A scheduled artisan command (`app:expire-inc-grades`) runs daily. It queries for grades where `is_inc = true` and `inc_expiration_date < today`. It automatically flips `is_inc = false`, sets `final_equivalent_grade = 5.0`, and changes `remarks` to 'Failed'.

## 7. Automatic Remarks
- **Auto-generation**: The backend fully manages the `remarks` column based on the computed grades and statuses. Instructors do not manually input remarks.
  - `1.0` - `3.0` -> 'Passed'
  - `4.0` -> 'Removal'
  - `5.0` -> 'Failed'
  - `is_inc=true` -> 'Incomplete'
  - Dropped logic -> 'Withdrawn' or 'Failed'
