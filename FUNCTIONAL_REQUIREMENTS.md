# Functional Requirements — Student Information Management REST API

This turns `ACTIVITY_INSTRUCTIONS.md` §8.1 (Required Endpoint Groups), §9 (Response Consistency), §10 (Validation), and §12.2 (Authorization) into one per-endpoint checklist: what it does, who's allowed to call it, what makes a request valid, and what the response looks like on success and failure.

**How to use this:** check an endpoint off once you have a passing feature test for it (matches the "Full TDD" approach in `DEV_GUIDE.md`) — not before. An endpoint that returns `200` but skips validation or authorization isn't done; every row's "Key Validation" and "Allowed Roles" columns are part of the definition of done, not optional polish.

## Conventions used in every table below

- **Base path:** `/api/v1/...` (spec §8).
- **Success envelope** (spec §9): `{"success": true, "message": "...", "data": {...}}`. Collections additionally carry pagination metadata (see spec §11 — covered in Phase 6, not detailed per-endpoint here).
- **Error envelope** (spec §9): `{"success": false, "message": "...", "errors": {...}}` for validation errors; `errors` omitted for non-validation failures.
- **Status codes** (spec §8.2): `200` read/update, `201` create, `204` delete (no body) or `200` (with body, if you prefer confirming what was deleted/deactivated), `401` not authenticated, `403` authenticated but not allowed, `404` doesn't exist, `409`/`422` conflict or validation failure.
- **"Allowed Roles"** reflects spec §12.2's table applied to this schema's actual `users.role` values (`administrator`, `registrar`, `instructor`, `student`) and the `DATA_DICTIONARY.md` schema. A few of these are judgment calls where the spec doesn't spell out the exact rule — flagged in **Open Decisions** at the bottom. Don't treat this column as unquestionable; confirm it matches what you actually intend.

---

## Authentication

- [ ] `POST /api/v1/auth/login`
- [ ] `POST /api/v1/auth/logout`
- [ ] `GET /api/v1/auth/me`

| Endpoint | Allowed Roles | Key Validation | Success | Errors |
|---|---|---|---|---|
| `POST /auth/login` | Public (no token required) | `email` required, valid format; `password` required. | `200`, returns user + Sanctum token. | `422` missing fields; `401` wrong credentials. Never reveal whether the email or the password was wrong. |
| `POST /auth/logout` | Any authenticated role | Valid Sanctum token in `Authorization` header. | `200`/`204`, current token revoked. | `401` no/invalid token. |
| `GET /auth/me` | Any authenticated role | Valid Sanctum token. | `200`, returns the authenticated user (no `password` field — spec §13). | `401` no/invalid token. |

This is the endpoint your Phase 1 `ApplicationBootTest` already proves is wired (`GET /api/user` → `401` unauthenticated) — Phase 3 is building out login itself and renaming/aliasing that route under `/api/v1/auth/me`.

---

## Students

- [ ] `GET /api/v1/students`
- [ ] `POST /api/v1/students`
- [ ] `GET /api/v1/students/{id}`
- [ ] `PUT`/`PATCH /api/v1/students/{id}`
- [ ] `DELETE /api/v1/students/{id}`

| Endpoint | Allowed Roles | Key Validation | Success | Errors |
|---|---|---|---|---|
| `GET /students` | Admin, Registrar | Query params: `search`, `program_id`, `year_level`, `status`, `page`, `per_page` (spec §11) all optional. | `200`, paginated list. | `401`, `403`. |
| `POST /students` | Admin, Registrar | `student_number` required, unique; `first_name`, `last_name`, `birth_date`, `program_id`, `year_level` required; `program_id` must reference an existing program; `email` valid format if supplied; `status` in `regular`\|`irregular`\|`extendee`. | `201`, returns created student. | `422` validation failure (incl. duplicate `student_number` — spec §16 explicitly requires this test case); `401`, `403`. |
| `GET /students/{id}` | Admin, Registrar (any); Student (**own record only** — object-level check per spec §12.2) | — | `200`. | `404` not found; `403` a student requesting another student's `id`. |
| `PUT`/`PATCH /students/{id}` | Admin, Registrar | Same field rules as create, but `student_number` uniqueness check must exclude the current record. | `200`, returns updated student. | `422`, `404`, `401`, `403`. |
| `DELETE /students/{id}` | Admin, Registrar | Consider soft-delete/deactivate (`status`) instead of a hard delete, since `students.id` is referenced by `enrollments` — a hard delete would be blocked by `restrictOnDelete` on `program_id`'s side, but you still need a plan for a student with existing enrollments. | `200`/`204`. | `404`, `401`, `403`, `409` if you keep a hard-delete and it's blocked by existing enrollments. |

---

## Programs

- [ ] `GET`/`POST /api/v1/programs`
- [ ] `GET`/`PUT`/`PATCH`/`DELETE /api/v1/programs/{id}`

| Endpoint | Allowed Roles | Key Validation | Success | Errors |
|---|---|---|---|---|
| `GET /programs`, `GET /programs/{id}` | Any authenticated role (reference data every role needs — e.g. to populate a student-creation form) | — | `200`. | `401`, `404` for the single-record form. |
| `POST /programs` | Admin, Registrar | `code` required, unique; `name` required; `status` in `active`\|`inactive`. | `201`. | `422` (incl. duplicate `code`), `401`, `403`. |
| `PUT`/`PATCH /programs/{id}` | Admin, Registrar | Same, `code` uniqueness excludes current record. | `200`. | `422`, `404`, `401`, `403`. |
| `DELETE /programs/{id}` | Admin | `program_id` on `students` is `restrictOnDelete` — a program with any students can't be deleted. | `200`/`204`. | `409` if students reference it, `404`, `401`, `403`. |

---

## Courses

- [ ] `GET`/`POST /api/v1/courses`
- [ ] `GET`/`PUT`/`PATCH`/`DELETE /api/v1/courses/{id}`

| Endpoint | Allowed Roles | Key Validation | Success | Errors |
|---|---|---|---|---|
| `GET /courses`, `GET /courses/{id}` | Any authenticated role | — | `200`. | `401`, `404`. |
| `POST /courses` | Admin, Registrar | `course_code` required, unique (spec §7.2); `course_title`, `units` required; `status` in `active`\|`inactive`. | `201`. | `422` (incl. duplicate `course_code`), `401`, `403`. |
| `PUT`/`PATCH /courses/{id}` | Admin, Registrar | Same, `course_code` uniqueness excludes current record. | `200`. | `422`, `404`, `401`, `403`. |
| `DELETE /courses/{id}` | Admin | `course_id` on `course_offerings` is `restrictOnDelete`. | `200`/`204`. | `409` if offerings reference it, `404`, `401`, `403`. |

---

## Academic Terms

- [ ] `GET`/`POST /api/v1/academic-terms`
- [ ] `GET`/`PUT`/`PATCH`/`DELETE /api/v1/academic-terms/{id}`

| Endpoint | Allowed Roles | Key Validation | Success | Errors |
|---|---|---|---|---|
| `GET /academic-terms`, `GET /academic-terms/{id}` | Any authenticated role | — | `200`. | `401`, `404`. |
| `POST /academic-terms` | Admin, Registrar | `academic_year`, `term` (`First Semester`\|`Second Semester`\|`MidYear`), `start_date`, `end_date` required; `end_date` after `start_date`; `(academic_year, term)` pair unique. | `201`. | `422` (incl. duplicate year+term pair), `401`, `403`. |
| `PUT`/`PATCH /academic-terms/{id}` | Admin, Registrar | Same, pair-uniqueness excludes current record. | `200`. | `422`, `404`, `401`, `403`. |
| `DELETE /academic-terms/{id}` | Admin | `academic_term_id` on `course_offerings` is `restrictOnDelete`. | `200`/`204`. | `409` if offerings reference it, `404`, `401`, `403`. |

---

## Course Offerings

- [ ] `GET`/`POST /api/v1/course-offerings`
- [ ] `GET`/`PUT`/`PATCH`/`DELETE /api/v1/course-offerings/{id}`

| Endpoint | Allowed Roles | Key Validation | Success | Errors |
|---|---|---|---|---|
| `GET /course-offerings`, `GET /course-offerings/{id}` | Any authenticated role (students browse to enroll; instructors see their own) | — | `200`. | `401`, `404`. |
| `POST /course-offerings` | Admin, Registrar | `course_id`, `academic_term_id`, `instructor_id` required and must reference existing records; `instructor_id` should reference a user with `role = instructor` (schema's FK alone doesn't check the role — this is an app-layer rule); `section`, `schedule`, `capacity` required; `(course_id, academic_term_id, section)` unique. | `201`. | `422` (incl. duplicate section, non-instructor `instructor_id`), `401`, `403`. |
| `PUT`/`PATCH /course-offerings/{id}` | Admin, Registrar | Same rules, uniqueness excludes current record. | `200`. | `422`, `404`, `401`, `403`. |
| `DELETE /course-offerings/{id}` | Admin | `course_offering_id` on `enrollments` is `restrictOnDelete`. | `200`/`204`. | `409` if enrollments reference it, `404`, `401`, `403`. |

---

## Enrollments

- [ ] `GET`/`POST /api/v1/enrollments`
- [ ] `GET`/`PATCH`/`DELETE /api/v1/enrollments/{id}`
- [ ] `GET /api/v1/students/{id}/enrollments`
- [ ] `GET /api/v1/course-offerings/{id}/students`

| Endpoint | Allowed Roles | Key Validation | Success | Errors |
|---|---|---|---|---|
| `GET /enrollments` | Admin, Registrar | — | `200`, paginated. | `401`, `403`. |
| `POST /enrollments` | Admin, Registrar (see **Open Decisions** re: student self-enrollment) | `student_id`, `course_offering_id` required, must reference existing records; `(student_id, course_offering_id)` pair must not already exist (spec §7.2/§16 — this is the DB unique constraint's job, but the endpoint must turn the resulting `QueryException` into a clean `409`/`422`, not leak it); offering's `capacity` shouldn't be exceeded (not currently enforced by the schema — app-layer check) and its `status` should be `open`. | `201`. | `422`/`409` duplicate enrollment (spec §16 explicit test case) or full/closed offering; `401`, `403`. |
| `GET /enrollments/{id}` | Admin, Registrar (any); Student (own only); Instructor (only for their own course offering) | — | `200`. | `404`, `401`, `403` object-level violation. |
| `PATCH /enrollments/{id}` | Admin, Registrar | Typically a `status` change (`enrolled`→`dropped`/`completed`). | `200`. | `422`, `404`, `401`, `403`. |
| `DELETE /enrollments/{id}` | Admin | `enrollment_id` on `grades` is `cascadeOnDelete` — deleting an enrollment silently deletes its grade record too. Confirm that's actually what you want before implementing a hard delete here; a status change to `dropped` may be the safer operation. | `200`/`204`. | `404`, `401`, `403`. |
| `GET /students/{id}/enrollments` | Admin, Registrar (any student); Student (self only) | — | `200`. | `403` object-level violation, `404`. |
| `GET /course-offerings/{id}/students` | Admin, Registrar; Instructor (only if `{id}` is one of their own offerings) | — | `200`. | `403` object-level violation, `404`. |

---

## Grades

- [ ] `GET`/`POST /api/v1/grades`
- [ ] `GET`/`PUT`/`PATCH /api/v1/grades/{id}`
- [ ] `GET /api/v1/students/{id}/grades`

| Endpoint | Allowed Roles | Key Validation | Success | Errors |
|---|---|---|---|---|
| `GET /grades` | Admin, Registrar | — | `200`, paginated. | `401`, `403`. |
| `POST /grades` | Instructor (only for enrollments under their own course offering — spec §12.2's central object-level rule); Admin/Registrar as an override | `enrollment_id` required, must reference an existing enrollment with no existing grade record (unique per `DATA_DICTIONARY.md`); `midterm_raw_score`/`final_raw_score` numeric within your defined range (0–100), or omitted entirely if setting `status = INCOMPLETE` instead. | `201`. | `422` (incl. out-of-range score, unauthorized instructor for that offering — spec §16 explicit test case), `401`, `403`, `404` bad `enrollment_id`. |
| `GET /grades/{id}` | Admin, Registrar (any); Instructor (own offering's grade); Student (own grade) | — | `200`, include the resolved `grade_point`/`remarks` from the linked `grade_scales` row, not just the raw score. | `404`, `401`, `403` object-level violation. |
| `PUT`/`PATCH /grades/{id}` | Instructor (own offering only), Admin/Registrar override | Submitting a raw score looks up the matching `grade_scales` band server-side and sets `midterm_grade_scale_id`/`final_grade_scale_id` — the client sends a raw score, never a `grade_point` or `grade_scale_id` directly. Setting `status` (e.g. `INCOMPLETE`) should be mutually exclusive with setting that period's `*_grade_scale_id`. | `200`. | `422` (incl. no matching band for the raw score — this needs your `grade_scales` ranges to be exhaustive, per the earlier design discussion), `404`, `401`, `403`. |
| `GET /students/{id}/grades` | Admin, Registrar (any); Student (self only); Instructor (only the subset within their own offerings) | — | `200`. | `403` object-level violation, `404`. |

---

## Academic Record

- [ ] `GET /api/v1/students/{id}/academic-record`

| Endpoint | Allowed Roles | Key Validation | Success | Errors |
|---|---|---|---|---|
| `GET /students/{id}/academic-record` | Admin, Registrar (any); Student (self only) | Aggregates a student's enrollments + grades, grouped by `academic_term_id` (spec §6). | `200`, nested by term. | `403` object-level violation, `404`. |

---

## Open Decisions

These aren't spelled out precisely enough by the spec to have one obviously-correct answer — the table above states a default, but confirm it matches what you actually intend before building against it:

1. **Does a Student self-enroll, or does Registrar/Admin enroll them?** The spec doesn't say either way. The table above defaults to staff-managed (`POST /enrollments` restricted to Admin/Registrar), matching §5.1's "Registrar/Staff... manage... enrollments." If you want self-service enrollment, `student` needs to be added to that endpoint's allowed roles, plus an object-level check that a student can only create an enrollment for themselves.
2. **Is there a `/users` CRUD endpoint group at all?** Spec §8.1 doesn't list one, but §6 requires a "Users and Roles" module, and someone has to create the `instructor`/`registrar` accounts referenced by `course_offerings.instructor_id`. You'll need *some* way to create users — either a dedicated Admin-only `/users` endpoint group (not in §8.1 but reasonable to add) or a seeder-only approach for the fixed set of staff accounts. Worth deciding explicitly rather than discovering the gap mid-Phase-4.
3. **Hard delete vs. deactivate**, called out per-resource above wherever a `restrictOnDelete` FK makes a literal `DELETE` awkward (Programs, Courses, Academic Terms, Course Offerings, Enrollments). Spec §8's table lists "Delete/deactivate" as one operation — it's explicitly leaving this choice to you.
