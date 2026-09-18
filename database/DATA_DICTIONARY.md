# Data Dictionary — Student Information Management REST API

Generated from the migrations in `database/migrations/` as of 2026-09-18. Covers the domain schema required by `ACTIVITY_INSTRUCTIONS.md` §7 (Minimum Data Model), plus the `grade_scales` table added to support normalized letter/point grading.

Enum columns are enforced at the database level (a fixed, short set of values). Plain `string` columns marked "app-validated" have no DB-level constraint on their contents — the valid set is enforced in a FormRequest, not the schema, because the set is more likely to grow or because it's derived data rather than a fixed vocabulary.

---

## `users`

Accounts for all four system roles (spec §5.1). Authentication is handled by Laravel + Sanctum; `password` is hashed automatically via the model's cast.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | bigint (PK) | no | — | |
| `name` | string | no | — | Display name. |
| `email` | string | no | — | **Unique.** Login identifier. |
| `email_verified_at` | timestamp | yes | null | Unused unless email verification is added. |
| `password` | string | no | — | Hashed (bcrypt) via the `User` model's cast. |
| `remember_token` | string | yes | null | Laravel's "remember me" cookie token. |
| `role` | enum | no | `student` | One of `administrator`, `registrar`, `instructor`, `student` (spec §5.1's four roles). Drives Gates & Policies in Phase 7. |
| `status` | enum | no | `active` | `active` \| `inactive`. An inactive user should be denied authentication. |
| `created_at`, `updated_at` | timestamp | yes | null | |

**Relationships:** a user with `role = instructor` is referenced by `course_offerings.instructor_id`.

---

## `programs`

Academic programs (e.g. degree programs) students are enrolled under.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | bigint (PK) | no | — | |
| `code` | string | no | — | **Unique.** Short program identifier (e.g. `BSCS`). |
| `name` | string | no | — | Full program name. |
| `description` | text | yes | null | |
| `status` | enum | no | `active` | `active` \| `inactive`. |
| `created_at`, `updated_at` | timestamp | yes | null | |

**Relationships:** one program has many `students` (`students.program_id`).

---

## `courses`

The catalog of courses that can be offered in a given term.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | bigint (PK) | no | — | |
| `course_code` | string | no | — | **Unique.** Per spec §7.2, must be unique. |
| `course_title` | string | no | — | |
| `description` | text | yes | null | |
| `units` | unsigned tinyint | no | — | Credit units. |
| `status` | enum | no | `active` | `active` \| `inactive`. |
| `created_at`, `updated_at` | timestamp | yes | null | |

**Relationships:** one course may have many `course_offerings` (`course_offerings.course_id`).

---

## `academic_terms`

A school year + semester period in which courses are offered.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | bigint (PK) | no | — | |
| `academic_year` | string | no | — | e.g. `"2026-2027"`. |
| `term` | enum | no | — | `First Semester` \| `Second Semester` \| `MidYear`. |
| `start_date` | date | no | — | |
| `end_date` | date | no | — | |
| `status` | enum | no | `active` | `active` \| `inactive`. |
| `created_at`, `updated_at` | timestamp | yes | null | |

**Constraints:** unique on `(academic_year, term)` — the same term can't be defined twice for the same academic year.

**Relationships:** one academic term may contain many `course_offerings` (`course_offerings.academic_term_id`).

---

## `students`

Student profiles. Note: a `student` row is a separate record from a `users` row with `role = student` — the schema doesn't currently link them (no `user_id` FK).

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | bigint (PK) | no | — | |
| `student_number` | string | no | — | **Unique** (spec §7.2, required). |
| `first_name` | string | no | — | |
| `middle_name` | string | yes | null | |
| `last_name` | string | no | — | |
| `suffix` | string | yes | null | e.g. `Jr.`, `III`. |
| `birth_date` | date | no | — | |
| `email` | string | yes | null | Not validated for uniqueness — see note below. |
| `contact_number` | string | yes | null | |
| `address` | string | yes | null | |
| `program_id` | FK → `programs.id` | no | — | `restrictOnDelete` — a program in use can't be deleted. |
| `year_level` | unsigned tinyint | no | — | |
| `status` | enum | no | `regular` | `regular` \| `irregular` \| `extendee` — academic standing, not an active/inactive account flag. |
| `created_at`, `updated_at` | timestamp | yes | null | |

**Relationships:** one student has many `enrollments` (`enrollments.student_id`).

**Note:** `email` has no unique constraint. Spec §10 only requires format validation ("valid email format when supplied"), not uniqueness, for students.

---

## `course_offerings`

A specific section of a course scheduled within a term, taught by an instructor.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | bigint (PK) | no | — | |
| `course_id` | FK → `courses.id` | no | — | `restrictOnDelete`. |
| `academic_term_id` | FK → `academic_terms.id` | no | — | `restrictOnDelete`. |
| `instructor_id` | FK → `users.id` | no | — | `restrictOnDelete`. Expected to reference a user with `role = instructor`, but the FK itself doesn't enforce the role — that's an app-layer check. |
| `section` | string | no | — | e.g. `A`, `B1`. |
| `schedule` | string | no | — | e.g. `MWF 9:00–10:00`. |
| `room` | string | yes | null | |
| `capacity` | unsigned int | no | — | Maximum enrollees. |
| `status` | enum | no | `open` | `open` \| `closed` \| `cancelled`. |
| `created_at`, `updated_at` | timestamp | yes | null | |

**Constraints:** unique on `(course_id, academic_term_id, section)` — the same section of the same course can't be offered twice in the same term.

**Relationships:** one course offering may have many `enrollments` (`enrollments.course_offering_id`).

---

## `enrollments`

A student's registration in a specific course offering.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | bigint (PK) | no | — | |
| `student_id` | FK → `students.id` | no | — | `restrictOnDelete`. |
| `course_offering_id` | FK → `course_offerings.id` | no | — | `restrictOnDelete`. |
| `enrollment_date` | date | no | — | |
| `status` | enum | no | `enrolled` | `enrolled` \| `dropped` \| `completed`. |
| `created_at`, `updated_at` | timestamp | yes | null | |

**Constraints:** unique on `(student_id, course_offering_id)` — a student can only enroll once in the same course offering. This is the DB-level enforcement for spec §7.2's "duplicate enrollment must be prevented" requirement.

**Relationships:** an enrollment may have one `grades` record (`grades.enrollment_id`).

---

## `grade_scales`

Reference table mapping a raw numeric score range to a normalized grade point. Defines the institution's grading scale independently of any one student's grade.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | bigint (PK) | no | — | |
| `min_score` | decimal(5,2) | no | — | Lower bound of the raw-score band (inclusive). |
| `max_score` | decimal(5,2) | no | — | Upper bound of the raw-score band (inclusive). |
| `grade_point` | decimal(3,2) | no | — | **Unique.** The normalized point value this band resolves to (e.g. `1.00`–`5.00` scale). |
| `remarks` | enum | no | — | `PASSED` \| `FAILED` \| `CONDITIONAL`. |
| `created_at`, `updated_at` | timestamp | yes | null | |

**Note:** nothing in the schema prevents two bands from having overlapping `min_score`/`max_score` ranges — that has to be checked in application code/validation when a band is created or edited.

**Relationships:** referenced by `grades.midterm_grade_scale_id` and `grades.final_grade_scale_id`.

---

## `grades`

A student's midterm and final results for one enrollment. Deliberately does *not* store `grade_point`/`remarks` directly — those come from the `grade_scales` row a grade is linked to, so a grade and its remarks can never drift out of sync with each other.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | bigint (PK) | no | — | |
| `enrollment_id` | FK → `enrollments.id` | no | — | **Unique** (one grade record per enrollment), `cascadeOnDelete`. |
| `midterm_raw_score` | decimal(5,2) | yes | null | Raw score as entered by the instructor. |
| `midterm_grade_scale_id` | FK → `grade_scales.id` | yes | null | `restrictOnDelete`. The band `midterm_raw_score` was matched to; `null` until a score is submitted. |
| `final_raw_score` | decimal(5,2) | yes | null | Same as `midterm_raw_score`, for the final period. |
| `final_grade_scale_id` | FK → `grade_scales.id` | yes | null | `restrictOnDelete`. Same as `midterm_grade_scale_id`, for the final period. |
| `status` | string (app-validated) | yes | null | Set (e.g. to `INCOMPLETE`) when a period couldn't be graded — i.e. when `midterm_grade_scale_id` or `final_grade_scale_id` is `null` despite the term having ended. Plain string rather than a DB enum, kept deliberately separate from `grade_scales.remarks` so the two vocabularies (grade classification vs. why a grade is missing) don't have to be kept in sync. |
| `created_at`, `updated_at` | timestamp | yes | null | |

**Note:** a period should never have both a `*_grade_scale_id` set *and* `status` describing why it's missing — that mutual exclusivity isn't enforced by the schema and needs to be a model/FormRequest rule.

---

## Framework / support tables

Not part of the domain model — created by Laravel's default scaffolding and the Sanctum package. Listed for completeness only.

| Table | Purpose |
|---|---|
| `password_reset_tokens` | Laravel's built-in password-reset flow (unused unless that feature is implemented). |
| `sessions` | Laravel's database session driver. |
| `cache` | Laravel's database cache driver. |
| `jobs` | Laravel's database queue driver. |
| `personal_access_tokens` | Sanctum API tokens — this is what actually backs `auth:sanctum` authentication. |

---

## Relationship summary (spec §7.1)

- One `program` → many `students`
- One `course` → many `course_offerings`
- One `academic_term` → many `course_offerings`
- One `user` (instructor) → many `course_offerings`
- One `student` → many `enrollments`
- One `course_offering` → many `enrollments`
- One `enrollment` → one `grades` record
- One `grade_scales` band → many `grades` (via `midterm_grade_scale_id` / `final_grade_scale_id`)
