# Development Guide — Student Information Management REST API

This is a living document. It tracks the full build against `ACTIVITY_INSTRUCTIONS.md` and expands one phase at a time — we detail the next phase once the current one is checked off, so the guide grows with the project instead of going stale.

## Decisions locked in

| Area | Choice | Why |
|---|---|---|
| Framework | Laravel 13 | Already scaffolded in this repo |
| Auth | Laravel Sanctum | Token-based, fits web/mobile/desktop API clients (spec §5) |
| Authorization | Native Gates & Policies | 4 fixed roles, no extra dependency, explainable at demo |
| Database (dev) | SQLite | Zero setup; swap via `.env` later if needed |
| API docs | Scramble | Auto-generates OpenAPI/Swagger UI from code (spec §17) |
| API client collection | Postman | Standard export, well-documented (spec §18) |
| Test framework | PHPUnit | Existing project convention (see CLAUDE.md) |
| Dev method | **Full TDD** | Every endpoint: failing test → minimal code to pass → refactor |

## How to use this guide

Each expanded phase gives you:
- **What** to build and **why**, tied to the spec section that requires it
- The **TDD sequence** — which test to write first, what it should assert, what "red" looks like before you've built anything
- **Illustrative snippets** for the non-obvious parts — not full solutions; you write the implementation
- The phase's **checkpoint** from spec §19 — the thing that proves the phase is actually done

Work top to bottom within a phase. Don't skip ahead to the next phase's tests until the current checkpoint passes.

---

## Roadmap (spec §19)

- [x] **Phase 1 — Project Setup**: framework, repo, environment, database connection
- [ ] **Phase 2 — Database Design**: ERD, migrations, constraints, relationships, seeders *(expanded below)*
- [ ] **Phase 3 — Authentication**: login, password hashing, protected routes, current user
- [ ] **Phase 4 — Core Resources**: Programs, Students, Courses, Academic Terms (CRUD + validation)
- [ ] **Phase 5 — Academic Transactions**: Course Offerings, Enrollments, Grades, Academic Record
- [ ] **Phase 6 — Advanced API Features**: search, filtering, sorting, pagination, consistent errors
- [ ] **Phase 7 — Authorization**: role-based + object-level access rules
- [ ] **Phase 8 — Documentation**: README, Scramble/OpenAPI, ERD, Postman collection
- [ ] **Phase 9 — Testing**: full positive/negative/validation/security pass, automated suite
- [ ] **Phase 10 — Final Demonstration**: local run-through of all 20 acceptance items (spec §22)

---

## Phase 1 — Project Setup *(complete)*

**Checkpoint (spec §19):** API server starts and database connection succeeds.

### 1.1 Set up your local environment

`.env` is gitignored (never committed — see spec §13), so a fresh clone won't have one. Create yours from the template and generate an app key:

```bash
cp .env.example .env
php artisan key:generate
```

The `.env.example` default already points `DB_CONNECTION` at `sqlite`. SQLite needs an actual database file to exist before anything can connect to it — Laravel won't create it for you:

```bash
touch database/database.sqlite   # PowerShell: New-Item -ItemType File database/database.sqlite
```

### 1.2 Confirm the base app boots

```bash
php artisan about
```

Confirm `Environment` is `local`, `Database` shows `sqlite` connected, and there are no errors.

### 1.3 Install Sanctum (auth) and Scramble (API docs)

```bash
composer require laravel/sanctum dedoc/scramble
php artisan install:api
```

`install:api` publishes Sanctum's migration, adds the `api` route group, and creates `routes/api.php` with `Route::middleware('auth:sanctum')` scaffolding — this is where every protected endpoint in later phases will live.

Scramble needs no publish step by default; once installed, visiting `/docs/api` renders OpenAPI docs generated from your routes/FormRequests/Resources as you build them in later phases.

### 1.4 Run the initial migration

```bash
php artisan migrate
```

This creates `users`, `cache`, `jobs`, and Sanctum's `personal_access_tokens` tables. Confirm no errors.

### 1.5 Configure PHPUnit for a clean, fast test DB

Laravel's default `phpunit.xml` should already set the testing environment to use an in-memory SQLite database (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) — check this in `phpunit.xml`. This matters for TDD: every test run gets a fresh, isolated database, and in-memory means it's fast enough to run constantly.

Verify the test suite currently runs (it'll be nearly empty, that's fine):

```bash
php artisan test
```

### 1.6 Write your first test — prove the harness works

Since we're doing full TDD from here on, Phase 1's own checkpoint should itself be backed by a test. Create a minimal smoke test:

```bash
php artisan make:test --phpunit ApplicationBootTest
```

```php
// tests/Feature/ApplicationBootTest.php
public function test_application_boots_and_connects_to_database(): void
{
    $response = $this->getJson('/api/user'); // default Sanctum-protected route from install:api

    $response->assertStatus(401); // unauthenticated — proves routing + auth middleware are wired, not that the route is public
}
```

Run it — it should currently **fail (red)** if `/api/user` doesn't exist yet or misbehaves, or **pass (green)** if `install:api`'s default scaffolding already wires it correctly. Either outcome tells you something real about your setup; don't move on until you understand which and why.

### Phase 1 checklist

- [x] `.env` created from `.env.example`, app key generated, SQLite file created
- [x] `php artisan about` shows no errors, SQLite connected
- [x] Sanctum installed, `routes/api.php` exists with `auth:sanctum` group
- [x] Scramble installed, `/docs/api` renders (even if empty)
- [x] `php artisan migrate` succeeds
- [x] `phpunit.xml` confirmed to use in-memory SQLite for tests
- [x] `ApplicationBootTest` written and passing
- [x] `php artisan serve` starts without error

Note: `.env` and `database/database.sqlite` are gitignored (the latter via `database/.gitignore`) — they're never committed, so step 1.1 has to be repeated on every fresh clone (including this one, and any other machine you set this project up on). This is expected and matches spec §14 (reproducibility via `.env.example`, not a committed `.env`).

**Verified 2026-09-18:** `php artisan about` shows `Database: sqlite` connected with no errors; `laravel/sanctum` (4.3.3) and `dedoc/scramble` (0.13.43) are installed direct dependencies; `routes/api.php:6` registers `GET|HEAD api/user` behind `auth:sanctum`; `docs/api` and `docs/api.json` routes are registered by Scramble; `migrate:status` shows all 4 migrations (including `personal_access_tokens`) ran; `phpunit.xml` sets `DB_DATABASE=:memory:`; `php artisan test` passes (3/3 assertions). Phase 1 is done — commit this work when you're ready.

---

## Phase 2 — Database Design *(expanded)*

**Checkpoint (spec §19):** Database can be recreated from project files (migrations + seeders, no manual steps beyond `php artisan migrate --seed`).

This phase is almost entirely schema: migrations, Eloquent relationships, and the constraints spec §7.2 calls out by name. "TDD" here means proving the schema with tests before/alongside writing it — a migration test that fails because the table doesn't exist yet, a relationship test that fails because the method doesn't exist yet, a uniqueness test that fails because the constraint isn't there yet.

### 2.1 Plan the ERD and migration order

Spec §7 gives you the entities and minimum fields; §7.1 gives you the relationships. Draw the ERD from that table before writing any migration — it's a required final output (spec §20.5) and it's much easier to get the foreign-key order right on paper first.

Migrations must run in dependency order (a migration can't reference a table that doesn't exist yet). Given the relationships in §7.1, the only valid order is roughly:

1. `programs` — no dependencies
2. `courses` — no dependencies
3. `academic_terms` — no dependencies
4. `students` — depends on `programs` (`program_id`)
5. `course_offerings` — depends on `courses`, `academic_terms`, and `users` (`instructor_id`)
6. `enrollments` — depends on `students`, `course_offerings`
7. `grades` — depends on `enrollments`

Laravel migration filenames are timestamp-ordered, so `make:migration` run in this sequence handles ordering for you automatically.

One thing not covered by Phase 1: `course_offerings.instructor_id` references `users`, and spec §5.1/§6 ties roles to users. You'll need a way to tell instructors apart from other users before this FK is meaningful — the simplest is a `role` column on `users` (string or enum: Admin/Registrar/Instructor/Student, matching the locked-in Gates & Policies decision). Add it now via a migration; the authorization *rules* built on top of it are Phase 7's job, not this one.

### 2.2 TDD sequence — work one entity at a time

For each entity, in the order above:

1. Write a feature/unit test first (it will fail — the migration and model don't exist yet).
2. `php artisan make:migration create_<table>_table`, fill in the schema.
3. `php artisan make:model <Model> -f` (the `-f` scaffolds a factory too).
4. Define relationships on the model (`belongsTo`, `hasMany`, etc. per §7.1).
5. Run the test — it should go green now.

**Illustrative example — `programs` (first, no dependencies):**

```php
// tests/Feature/ProgramTest.php
public function test_program_can_be_created_with_required_fields(): void
{
    $program = Program::factory()->create(['code' => 'BSCS']);

    $this->assertDatabaseHas('programs', ['code' => 'BSCS']);
}
```

**Illustrative example — the two constraints spec §7.2 names explicitly.** These deserve their own tests because they're the ones a naive migration is most likely to get wrong:

```php
// tests/Feature/StudentTest.php
public function test_student_number_must_be_unique(): void
{
    Student::factory()->create(['student_number' => '2026-00001']);

    $this->expectException(\Illuminate\Database\QueryException::class);
    Student::factory()->create(['student_number' => '2026-00001']);
}
```

```php
// tests/Feature/EnrollmentTest.php
public function test_duplicate_enrollment_in_same_course_offering_is_prevented(): void
{
    $offering = CourseOffering::factory()->create();
    $student = Student::factory()->create();
    Enrollment::factory()->create(['student_id' => $student->id, 'course_offering_id' => $offering->id]);

    $this->expectException(\Illuminate\Database\QueryException::class);
    Enrollment::factory()->create(['student_id' => $student->id, 'course_offering_id' => $offering->id]);
}
```

Both rely on a database-level constraint (`unique()` on the migration column, or a composite `unique(['student_id', 'course_offering_id'])`), not just application-level validation — spec §7.2 says data integrity, and only the DB layer guarantees that under concurrent requests. Application-level validation (returning a clean 422 instead of a raw exception) is Phase 4/6's job.

### 2.3 Relationships to wire up on the models

Per spec §7.1, once the migrations exist:

- `Program::students()` hasMany / `Student::program()` belongsTo
- `Course::courseOfferings()` hasMany / `CourseOffering::course()` belongsTo
- `AcademicTerm::courseOfferings()` hasMany / `CourseOffering::academicTerm()` belongsTo
- `User::courseOfferings()` hasMany (as instructor) / `CourseOffering::instructor()` belongsTo
- `Student::enrollments()` hasMany / `Enrollment::student()` belongsTo
- `CourseOffering::enrollments()` hasMany / `Enrollment::courseOffering()` belongsTo
- `Enrollment::grade()` hasOne / `Grade::enrollment()` belongsTo

A relationship test (`$student->program->code`) is a good green-light check that a `belongsTo`/`hasMany` pair is wired correctly, separate from the constraint tests above.

### 2.4 Seeders

Spec §20.3 requires seeders/fixtures/factories that can (re)populate a working dataset. Once factories exist for all seven entities (step 2.2 above gives you one per entity via `-f`), a `DatabaseSeeder` that creates a handful of programs, courses, terms, students, offerings, enrollments, and grades — in that dependency order — satisfies this. Keep it deterministic enough to be useful for manual testing (e.g., a known admin/instructor/student login), since Phase 3 auth testing and the final demo (§22) will lean on it.

### Phase 2 checklist

- [x] ERD drawn, covering all 7 entities + `users`, saved somewhere in the repo (spec §20.5)
- [x] All 7 migrations created in dependency order and run clean
- [x] `role` column added to `users`
- [ ] `student_number` unique constraint — test written and passing
- [ ] Duplicate-enrollment prevention constraint — test written and passing
- [x] `course_code` unique constraint in place
- [x] All foreign keys constrained (`->constrained()` / `->foreignId()`), not just plain columns
- [x] All relationships from §2.3 defined and covered by at least one passing test
- [x] Factories exist for all 7 entities
- [x] `DatabaseSeeder` populates a working, dependency-ordered dataset
- [x] `php artisan migrate:fresh --seed` runs clean from an empty database

Work through 2.1–2.4 yourself, test-first each entity. Come back once the full checklist is checked and I'll expand **Phase 3 — Authentication** — login/token issuance, password hashing, the `auth:sanctum` middleware you already have from Phase 1, and the TDD sequence for a protected "current user" endpoint.
