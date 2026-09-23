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
- [x] **Phase 3 — Authentication**: login, password hashing, protected routes, current user *(complete)*
- [x] **Phase 4 — Core Resources**: Programs, Courses, Academic Terms, Students (CRUD + validation) *(complete)*
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

> **Note on the checklist above (2026-09-20):** two boxes are still unchecked — the `student_number` uniqueness test and the duplicate-enrollment test. The database-level constraints themselves *are* in place (confirmed: `students` migration has `->unique()` on `student_number`, `enrollments` has a composite `unique(['student_id', 'course_offering_id'])`), so the checkpoint ("database can be recreated from project files") is genuinely satisfied. What's missing is just the two dedicated tests from §2.2 that prove those constraints under a duplicate-insert attempt. Not a blocker for Phase 3 — auth doesn't depend on them — but circle back before Phase 9 (Testing) since the spec grades on a full positive/negative/validation pass.

---

## Phase 3 — Authentication *(complete)*

**Checkpoint (spec §19):** Protected endpoint rejects unauthenticated request.

**Required endpoints (spec §8.1):**

| Action | Endpoint |
|---|---|
| Login | `POST /api/v1/auth/login` |
| Logout / token invalidation | `POST /api/v1/auth/logout` |
| Current user | `GET /api/v1/auth/me` |

### 3.0 Sanctum, explained from scratch

You haven't used Sanctum before, so before writing anything, here's what it actually is and why it's the right tool here. Official docs for the version installed in this project (Sanctum 4.3.3, Laravel 13.x): **[laravel.com/docs/13.x/sanctum](https://laravel.com/docs/13.x/sanctum)**.

**The problem it solves.** HTTP is stateless — every request arrives with no memory of previous ones. Spec §12 frames it as two separate questions: *authentication* ("who is this?") and *authorization* ("what are they allowed to do?", which is Phase 7's job). Phase 3 is only about the first question: proving identity on every request without a browser session, since this API has no frontend (spec line 21: "a graphical frontend is intentionally excluded").

**Why Sanctum, not something heavier like OAuth/Passport.** Sanctum actually bundles two unrelated features — see **[How it Works](https://laravel.com/docs/13.x/sanctum#how-it-works)**:
1. **API tokens** ("personal access tokens") — simple bearer tokens, like a GitHub personal access token. No handshake, no redirect flow, no client secrets.
2. **SPA cookie authentication** — for a first-party single-page app sharing a domain with the API.

This project only needs (1). There's no SPA, no mobile app yet, no browser session to piggyback on — just HTTP clients (Postman, later maybe a mobile/desktop client) sending a token with every request. That's exactly what personal access tokens are for. Ignore the SPA-Authentication and CORS/cookie sections of the docs entirely — they don't apply here.

**What a token actually is.** Read **[API Token Authentication → Issuing API Tokens](https://laravel.com/docs/13.x/sanctum#issuing-api-tokens)**. The flow:
1. Your login endpoint calls `$user->createToken('some-name')`, which Sanctum provides via the `HasApiTokens` trait — already added to `App\Models\User` back in Phase 1 (`app/Models/User.php:20`).
2. Sanctum generates a random plaintext string, hashes it with SHA-256, and stores *only the hash* in the `personal_access_tokens` table (that table was already migrated in Phase 1 — `database/migrations/2026_09_17_080858_create_personal_access_tokens_table.php`).
3. The plaintext value is returned to you **once**, on the `NewAccessToken` object's `plainTextToken` property. You send it to the client in the login response. Sanctum itself never stores it — if the client loses it, there's no "forgot my token" recovery, they just log in again.
4. On every later request, the client sends that plaintext string back in an HTTP header: `Authorization: Bearer <token>`.

**How a protected route checks the token.** The `auth:sanctum` middleware — already attached to `GET /user` in `routes/api.php` from Phase 1 — does this on every request: reads the `Authorization` header, hashes the presented token the same way, looks up a matching row in `personal_access_tokens`, and if found, resolves the owning `User` and attaches it to `$request->user()`. No match (missing header, garbage token, or a token that's been deleted) → the request never reaches your controller; Laravel's auth layer responds `401 Unauthorized` on its own. Docs: **[Protecting Routes](https://laravel.com/docs/13.x/sanctum#protecting-routes)**. This is exactly what `ApplicationBootTest` already proved in Phase 1 by asserting `401` on `/api/user` with no token.

**Token abilities — mentioned, not needed yet.** Sanctum lets you scope a token to specific "abilities" (`$user->createToken('name', ['posts:read'])`), checked via `$user->tokenCan('posts:read')`. See **[Token Abilities](https://laravel.com/docs/13.x/sanctum#token-abilities)**. Don't reach for this in Phase 3 — it's easy to confuse with the role-based authorization you're building in **Phase 7** (Gates & Policies on `role`), but it's a different axis entirely: abilities describe what a *token* is allowed to do; your app's roles describe what a *user* is allowed to do. This project's spec (§12.2) is role-based, so abilities stay unused here — noted only so you recognize the concept if you see it in the docs and don't conflate the two.

**Logout = revoking the token.** There's no server-side "session" to destroy — logout just deletes the token's row from `personal_access_tokens`, so the next request presenting that same plaintext string finds no match and gets `401`. Docs: **[Revoking Tokens](https://laravel.com/docs/13.x/sanctum#revoking-tokens)**. The relevant call is `$request->user()->currentAccessToken()->delete()` — this deletes *only* the token used to authenticate the current request, not every token the user has (a user could be logged in from two devices at once; logging out on one shouldn't kill the other).

**Password hashing.** Spec §12.1 requires framework-supported hashing. Laravel's `Hash` facade wraps bcrypt (the framework default) — docs: **[Hashing](https://laravel.com/docs/13.x/hashing)**. You don't actually need to call `Hash::make()` yourself when *creating* users: `App\Models\User::casts()` already declares `'password' => 'hashed'` (`app/Models/User.php:31`), so Eloquent hashes it automatically whenever you assign a plaintext value to `$user->password`. You *do* need `Hash::check($plaintext, $user->password)` yourself at login time, to verify a submitted password against the stored hash — hashes aren't reversible, so "checking" a password is always "hash the guess and compare," never "decrypt and compare."

**Testing authenticated requests.** Sanctum ships a test helper, `Sanctum::actingAs()`, that skips the real login flow and just marks a given user as the authenticated actor for the request — useful in later phases when you're testing a resource endpoint and don't want every test to also re-prove login works. Docs: **[Testing](https://laravel.com/docs/13.x/sanctum#testing)**. For Phase 3 itself, though, you're testing login *directly*, so most of these tests will do the real thing: POST credentials, read the token out of the response, then send that literal token on a follow-up request — see 3.2 below.

### 3.1 Add the versioned auth route group

`routes/api.php` currently only has the default `GET /user` route from `install:api`. Spec §8.1 needs everything under `/api/v1/...`, starting with:

```
POST /api/v1/auth/login   (public)
POST /api/v1/auth/logout  (protected)
GET  /api/v1/auth/me      (protected)
```

Note the split: `login` must be reachable *without* a token (that's the whole point — you don't have one yet), while `logout` and `me` require one. That means two groups under the same `v1` prefix — one plain, one wrapped in `auth:sanctum`. This is also the first "versioned" route group in the app, so however you structure it here (route file organization, prefix nesting) is the pattern Phase 4 onward will follow for every other resource.

### 3.2 TDD sequence

Work in this order — each test should be red for a specific, understood reason before you make it green:

1. **`GET /api/v1/auth/me` with no token.** Before the route exists at all, this returns `404` — that's an expected kind of "red," it just means "build the route." Once the route exists behind `auth:sanctum` but before you're logged in, it should return `401`. Both are useful signals; know which one you're looking at.
2. **Login with valid credentials returns a token.** Assert `200` and a token string present in the JSON body.
3. **Login with a wrong password is rejected.** Assert `422` (validation-style failure per spec §8.2) with a validation error — and assert the response body does *not* contain a token.
4. **`GET /api/v1/auth/me` with a valid token returns the authenticated user**, and critically, the response must **not** contain the `password` field (spec §13: "do not return password hashes... in API responses"). Note this is already partly guaranteed by `#[Hidden(['password', 'remember_token'])]` on `App\Models\User` (`app/Models/User.php:16`) — the test should confirm that attribute actually does its job when the model is returned as JSON, not just trust that it does.
5. **Logout revokes the token**, and a follow-up request reusing that same (now-deleted) token gets `401`.

**Illustrative example — login (#2 and #3), the least obvious ones to set up:**

```php
// tests/Feature/AuthenticationTest.php
public function test_user_can_login_with_valid_credentials(): void
{
    $user = User::factory()->create(['password' => Hash::make('correct-password')]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ]);

    $response->assertOk()->assertJsonStructure(['token']);
}

public function test_login_fails_with_incorrect_password(): void
{
    $user = User::factory()->create(['password' => Hash::make('correct-password')]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonMissingPath('token');
}
```

**Illustrative example — logout, which needs a real token to revoke (not `Sanctum::actingAs`, since that bypasses `personal_access_tokens` entirely):**

```php
// tests/Feature/AuthenticationTest.php
public function test_logout_revokes_the_token(): void
{
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me')
        ->assertStatus(401);
}
```

Write #1 and #4 yourself, following the same shape.

### 3.3 What to build (guidance, not the implementation)

You write this part. Shape to aim for, matching what you already know from Phase 1/2's conventions:

- `php artisan make:request LoginRequest` — validates `email` (`required|email`) and `password` (`required`). Keeps validation out of the controller, consistent with how you'll do it for every resource from Phase 4 onward.
- An `AuthController` with three methods: `login()`, `logout()`, `me()`.
  - `login()`: look the user up by email, `Hash::check()` the submitted password against `$user->password`, and on failure throw `ValidationException::withMessages([...])` so it surfaces as a `422` — don't reveal *which* field was wrong (email vs. password) in the message; that leaks whether an account exists. On success, `$user->createToken(...)->plainTextToken`, returned in the JSON body.
  - `logout()`: `$request->user()->currentAccessToken()->delete()`.
  - `me()`: return `$request->user()`.
- Sanctum's own **[Mobile Application Authentication → Issuing API Tokens](https://laravel.com/docs/13.x/sanctum#issuing-mobile-api-tokens)** section shows this exact credential-exchange pattern end to end — read it before writing `login()`; it's the closest match to what this API needs (issue a token in exchange for email+password, no session, no SPA).
- In `routes/api.php`, group these under `Route::prefix('v1')`, with `login` outside `auth:sanctum` and `logout`/`me` inside it.

### Phase 3 checklist

- [x] `routes/api.php` has a `v1` prefix group with `POST auth/login` (public), `POST auth/logout` and `GET auth/me` (both behind `auth:sanctum`)
- [x] `LoginRequest` validates `email` and `password`
- [x] Login: valid credentials → `200` + token — test written and passing
- [x] Login: wrong password → `422`, no token in response — test written and passing
- [x] `GET /api/v1/auth/me`: no token → `401`; valid token → `200` with the correct user and **no** `password` field — tests written and passing
- [x] Logout revokes the token; reusing it afterward → `401` — test written and passing
- [x] `php artisan test` green for the whole `AuthenticationTest` suite

**Verified 2026-09-20:** `php artisan test` passes (15 tests / 35 assertions), including all five `AuthenticationTest` cases. `php artisan route:list --path=v1` shows `POST api/v1/auth/login`, `POST api/v1/auth/logout` and `GET|HEAD api/v1/auth/me`, with `auth:sanctum` guarding the last two. The checkpoint ("protected endpoint rejects unauthenticated request") is met. Phase 3 is done — commit this work when you're ready.

> **Follow-ups (updated 2026-09-20)** — none of these block Phase 4:
> 1. ~~**Route names.**~~ *Resolved* — `route:list` now shows `auth.login`, `auth.logout` and `auth.me`. (For the record: `->name('auth.')` on a route *group* only sets a name **prefix**; each route still needs its own `->name('login')` etc.)
> 2. ~~**`me()` returned only `id` and `email`.**~~ *Resolved* — it now returns the user, so test #4's `assertJsonMissingPath('password')` genuinely proves `#[Hidden]` is doing its job.
> 3. **Formatting.** Run `vendor/bin/pint --dirty --format agent`; `AuthController.php` and `routes/api.php` still have trailing spaces and stray blank lines.
> 4. **Response envelope.** Phase 4 adopts the spec §9 envelope (`success` / `message` / `data`, see 4.1). `login` and `me` still return their original flat bodies, so they get retrofitted in **4.6** once Programs is working.

---

## Phase 4 — Core Resources *(complete)*

**Checkpoint (spec §19):** CRUD requests work with validation.

**Required endpoints (spec §8.1)** — four resources, all with the same shape:

| Resource | Collection | Single record |
|---|---|---|
| Programs | `GET`, `POST /api/v1/programs` | `GET`, `PUT`/`PATCH`, `DELETE /api/v1/programs/{id}` |
| Courses | `GET`, `POST /api/v1/courses` | `GET`, `PUT`/`PATCH`, `DELETE /api/v1/courses/{id}` |
| Academic Terms | `GET`, `POST /api/v1/academic-terms` | `GET`, `PUT`/`PATCH`, `DELETE /api/v1/academic-terms/{id}` |
| Students | `GET`, `POST /api/v1/students` | `GET`, `PUT`/`PATCH`, `DELETE /api/v1/students/{id}` |

**Out of scope for this phase** (so you don't build them early): search / filter / sort / `per_page` (Phase 6), role-based rules (Phase 7), nested routes like `/students/{id}/enrollments` (Phase 5). Every endpoint here simply requires a valid token.

**Response shapes are decided here, not in Phase 6.** Successful responses and **validation** errors (`422`) follow the spec §9 envelopes — see 4.1. The shape of the *other* error statuses (`401`, `403`, `404`, `409`, `500`) is still Phase 6's job; until then, tests assert only their status code.

### 4.0 The four building blocks, explained from scratch

**Resource routes.** One line registers the whole CRUD set — see **[API Resource Controllers](https://laravel.com/docs/13.x/controllers#api-resource-routes)**:

```php
Route::apiResource('programs', ProgramController::class);
```

| Verb | URI | Controller method | Route name | Success status |
|---|---|---|---|---|
| `GET` | `/programs` | `index` | `programs.index` | `200` |
| `POST` | `/programs` | `store` | `programs.store` | `201` |
| `GET` | `/programs/{program}` | `show` | `programs.show` | `200` |
| `PUT` / `PATCH` | `/programs/{program}` | `update` | `programs.update` | `200` |
| `DELETE` | `/programs/{program}` | `destroy` | `programs.destroy` | `204` |

`php artisan make:controller ProgramController --api` generates exactly these five methods (the plain `make:controller` also adds `create`/`edit`, which exist for HTML forms and don't apply to a JSON API). For a hyphenated resource like `Route::apiResource('academic-terms', AcademicTermController::class)`, the URL is `academic-terms` but the route parameter is `{academic_term}`; type-hint it as `AcademicTerm $academicTerm` and Laravel matches the two. If binding gives you a wrong or empty model, check that name first.

**Route model binding.** In `show`, `update` and `destroy`, type-hint the model — `public function show(Program $program)`. Laravel takes `{program}` from the URL, runs the equivalent of `Program::findOrFail($id)`, and a missing row becomes a `404` on its own. Docs: **[Route Model Binding](https://laravel.com/docs/13.x/routing#route-model-binding)**. Your `bootstrap/app.php` already forces JSON for `api/*`, so it's a JSON 404 — this is the "not found" case spec §16 lists, but it's your project's behaviour, so still test it once per resource.

**Form Requests, now for two actions.** You wrote `LoginRequest` in Phase 3. CRUD needs a *create* request and an *update* request, because the rules differ:

- **Store:** required fields are required; unique fields must be unique.
- **Update:** the `unique` rule must **ignore the record being edited**. Otherwise `PUT /programs/1` that resends its own unchanged `code` fails with "already taken". Docs: **[Rule::unique → Ignoring a Given ID](https://laravel.com/docs/13.x/validation#rule-unique)**.

```php
// UpdateProgramRequest::rules()  — illustrative shape, not the full rule set
'code' => ['required', 'string', Rule::unique('programs', 'code')->ignore($this->route('program'))],
```

- **PUT vs PATCH:** `apiResource` routes both to `update()`. PATCH is partial by definition (send only the fields you're changing). The simplest way to support both with one request class is to prefix the rules with `sometimes` ("only validate this field if it's present in the input"). Decide and document it.
- Remember from Phase 3: the `make:request` stub returns `false` from `authorize()`. Change it to `true`, or every request gets a `403` before validation runs. (Real permission rules arrive in Phase 7.)

**API Resources.** `php artisan make:resource ProgramResource` creates a class that turns a model into the JSON *you* choose. Docs: **[Eloquent: API Resources](https://laravel.com/docs/13.x/eloquent-resources)**. Why bother instead of returning the model: you control which fields appear (spec §13 — no unnecessary sensitive fields), and the JSON shape stops being tied to your column names.

```php
return new ProgramResource($program);            // one record  → {"data": {...}}
return ProgramResource::collection($paginator);  // many records → {"data": [...], "links": {...}, "meta": {...}}
```

Resources wrap output in a `data` key by default, and a paginator adds `links` and `meta` automatically. When a resource wraps a model that was *just created*, Laravel sets the status to `201` for you; you can also set it explicitly — the test asserts `201` either way.

**Adding `success` and `message` (the spec §9 envelope).** By default you only get `data`. A resource can add extra **top-level** keys beside it with `additional()`:

```php
return (new ProgramResource($program))->additional([
    'success' => true,
    'message' => 'Program retrieved successfully.',
]);
// → {"data": {...}, "success": true, "message": "Program retrieved successfully."}
```

`JsonResource` also has a `with()` method for the same purpose, but it lives on the resource *class*, so it can't vary per action — and `message` differs for `index`, `show`, `store` and `update`. Per-call `additional()` fits better. Where you put it is your call: repeat it in each controller method, or write one small helper on the base `Controller`; the tests don't care which. On a paginated `index`, `links` and `meta` stay beside `data`, `success` and `message`.

### 4.1 Decisions made up front

These are choices the spec leaves open. They're settled now so all four resources stay consistent (and so `ProgramApiTest` has a fixed contract to test against).

| Decision | Choice | Why |
|---|---|---|
| **Success response shape** | The spec §9 envelope on every successful response except `204`: `success: true`, `message`, `data`. Built with an API Resource plus `additional()` (see 4.0). | Your decision — the spec asks for a predictable, documented format. Login and `me` are brought in line in 4.6. |
| **Validation error shape** | The spec §9 error envelope, status `422`: `success: false`, `message: "Validation failed."`, `errors: { field: [ ...messages ] }`. Built **once, globally** (see "The validation error shape" below). | Your decision. One handler means every present and future endpoint gets the same shape. |
| **Delete response** | `204 No Content`, no body. | A `204` can't carry a body, and spec §8.2 explicitly allows it "when appropriate". |
| **Deleting a record that's still referenced** | Return `409 Conflict` and don't delete. (Details below.) | Otherwise the database refuses and you return a `500`. |
| **Authentication in tests** | `Sanctum::actingAs(User::factory()->administrator()->create())` | An administrator may do everything (spec §12.2), so Phase 7's rules won't break your Phase 4 tests. |
| **Collection endpoints** | `ProgramResource::collection(Program::paginate())` from the start. | Spec §11: collections must not be unbounded. See "Pagination, in short" below. |

**Message wording.** Follow the spec's own example ("Student retrieved successfully.") — `<Resource> <verb> successfully.`, with the plural for a list:

| Action | `message` |
|---|---|
| `index` | `Programs retrieved successfully.` |
| `show` | `Program retrieved successfully.` |
| `store` | `Program created successfully.` |
| `update` | `Program updated successfully.` |

Message text is part of your API's contract, which is why `ProgramApiTest` asserts it exactly — change the wording in the tests and the code together.

**The validation error shape.** Laravel's default `422` body has `message` and `errors` but no `success`. To get the spec's shape everywhere at once, render `ValidationException` yourself in `bootstrap/app.php`, inside the `withExceptions` callback you already have (it currently only calls `shouldRenderJsonWhen`). Docs: **[Errors → Rendering Exceptions](https://laravel.com/docs/13.x/errors#rendering-exceptions)**. The skeleton:

```php
$exceptions->render(function (ValidationException $e, Request $request) {
    // return a JSON response with status 422 and the keys
    // success (false), message ("Validation failed."), errors (from $e->errors())
});
```

Restrict it to API requests (`$request->is('api/*')`), as your `shouldRenderJsonWhen` rule does. Two consequences to expect:

- **It also reshapes the Phase 3 login failure.** The `message` becomes `"Validation failed."`, and your "The provided credentials are incorrect." text moves into `errors.email`. `AuthenticationTest` #3 only asserts that `message` and `errors` exist, so it keeps passing.
- **Only validation errors are covered.** `401`, `403`, `404`, `409` and `500` keep Laravel's default bodies until Phase 6; tests assert their status code only.

**Pagination, in short.** A collection endpoint (`index`) returns a list, and `paginate()` returns it one page at a time — **15 records per page by default**, page chosen by `?page=N` — instead of every row, which would grow without limit as data grows. Wrapped in a resource collection, the response carries `data` (this page's records) plus `links` (first/last/prev/next URLs) and `meta` (`current_page`, `per_page`, `total`, `last_page`, …) — the pagination metadata spec §11 asks for. Docs: **[Database: Pagination](https://laravel.com/docs/13.x/pagination)**. Choosing it now costs one method call per `index`; adding it later means finding every unbounded query, and any client that assumed "all records" would silently start getting only the first 15. The client-controlled page size (`?per_page=`), search, filtering and sorting are Phase 6.

**Testing pagination needs more than 15 records.** With 3 records, or exactly 15, a paginated `index` and an unbounded one return the same thing, so the test couldn't tell them apart. Create **16** and assert page 1 holds 15 while `meta.total` is 16. Watch your factories: `ProgramFactory` picks from 12 fixed programs with `fake()->unique()`, so `Program::factory()->count(16)` throws an overflow — `ProgramApiTest` inserts the 16 rows directly instead.

**The delete decision, in detail.** Your migrations use `restrictOnDelete()` on `students.program_id`, `course_offerings.course_id`, `course_offerings.academic_term_id` and `enrollments.student_id`. So `DELETE /api/v1/programs/1` on a program that has students makes the database refuse, and an unhandled `QueryException` becomes a `500` — spec §8.2 reserves `500` for *unexpected* failures and §13 says not to leak internals. The database constraint is your safety net; the controller should notice first. Two spec-compatible options (spec §8: "Delete/deactivate"):

- **(a) Check dependants, then `409` or delete.** e.g. `$program->students()->exists()` → `409`; otherwise delete and return `204`. Spec §8.2 lists `409` for "conflicting state". The relationships already exist from Phase 2 (`Program::students()`, `Course::courseOfferings()`, `AcademicTerm::courseOfferings()`, `Student::enrollments()`). **Recommended.**
- **(b) Never hard-delete; set `status` to `inactive`.** Sidesteps the foreign keys, but then you must decide what `DELETE` and the next `GET` return.

### 4.2 What to build (guidance, not the implementation)

You write this part. For each of the four resources, the commands are (shown for programs):

```
php artisan make:controller ProgramController --api
php artisan make:request StoreProgramRequest
php artisan make:request UpdateProgramRequest
php artisan make:resource ProgramResource
```

Routes go inside the `v1` group, wrapped in `auth:sanctum`. Put this **at the `v1` level, not inside your `auth` prefix group** — otherwise the URL becomes `/api/v1/auth/programs`:

```php
Route::prefix('v1')->group(function () {
    // ...your existing auth routes...

    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('programs', ProgramController::class);
    });
});
```

Controller method shapes (each success response also carries `success` and `message` via `additional()` — see 4.0 and the wording table in 4.1):

- `index` — `paginate()` the model, return `Resource::collection(...)`.
- `store` — `Model::create($request->validated())`, return the resource (`201`). Mass assignment is safe here because your models declare `#[Fillable([...])]`; never pass `$request->all()`.
- `show` — `return new Resource($model)`.
- `update` — `$model->update($request->validated())`, return the resource (`200`).
- `destroy` — dependants exist → `409` with a clear message; otherwise delete and return `response()->noContent()` (`204`, no body, so no envelope).

**Build order for Programs.** Do the one-off global work first, because every Program test that expects a `422` depends on it:

1. Register `Route::apiResource('programs', ProgramController::class)` at the `v1` level inside `auth:sanctum` — the `404`s become `401`s for the unauthenticated tests, and empty-bodied `200`s for the rest (the scaffolded controller methods return nothing yet).
2. Add the global `ValidationException` renderer in `bootstrap/app.php` (see "The validation error shape" in 4.1).
3. Then work through the tests in the 4.4 order: resource, requests, controller methods.

### 4.3 Validation rules per resource

Derived from your migrations and spec §10. The database enums and `unsignedTinyInteger` columns don't give you a friendly error — a bad value throws a database exception (a `500`) unless a rule catches it first. **Your rules are what turn "bad input" into a `422`.**

| Resource | Field → rule ideas |
|---|---|
| **Programs** | `code`: required, string, unique. `name`: required, string, max 255. `description`: nullable string. `status`: optional, `in:active,inactive` (DB default is `active`). |
| **Courses** | `course_code`: required, string, unique. `course_title`: required, string, max 255. `description`: nullable string. `units`: required, integer, **with a range you choose and document** — the column is `unsignedTinyInteger` (0–255), so the database alone won't stop `units: 200`; your factory uses 3–5. `status`: `in:active,inactive`. |
| **Academic Terms** | `academic_year`: required, string, format `YYYY-YYYY` **with consecutive years** — built as a custom rule class (`app/Rules/ConsecutiveAcademicYear.php`, its own unit test in `tests/Unit/Rules/`), not a bare `regex`, since "is this a valid pair of years" is real logic worth naming and testing on its own. `term`: required, `in:First Semester,Second Semester,MidYear`. `start_date`/`end_date`: `date_format:Y-m-d`, `end_date` `after:start_date`. `status`: `in:active,inactive`. The `(academic_year, term)` pair is unique — `Rule::unique(...)->where(...)`, error reported on `term`. **Update is partial-friendly**: a field not sent falls back to the record's stored value for the cross-field checks (date order, the pair), so `PATCH {"status":"inactive"}` alone works without resending everything. |
| **Students** | `student_number`: required, string, unique. `first_name`, `last_name`: required, string. `middle_name`, `suffix`: nullable string. `birth_date`: required date, `before:today`. `email`: **required**, `email`, **unique** — spec §10 only asked for "valid email format when supplied", but the schema was deliberately tightened (see the note below the table) so it's now mandatory and unique like a real student record. `contact_number`: **required, unique** (same reasoning). `address`: nullable string. `program_id`: required, `exists:programs,id` (spec §16 "invalid references"). `year_level`: required integer, `between:1,4` — your factory only generates 1–4, so that's the range the API enforces; widen it only if you widen the factory too. `status`: `in:regular,irregular,extendee`. |

> **Schema change (2026-09-23):** the original `students` migration left `email` and `contact_number` nullable and non-unique, matching spec §10's minimum. A later decision tightened both to `NOT NULL` + `UNIQUE` via an additive migration (`add_constraints_to_students_table`) — student contact fields are effectively personal identifiers, and DB-level uniqueness protects them under concurrent requests the same way `student_number` and `course_code` already are. `StudentFactory` was updated to always generate a unique email and phone number (`fake()->unique()->numerify('09#########')` for the phone — see the pagination gotcha below).

Keep each field's allowed values in one place (a constant on the model, for example) so the store and update requests can't drift apart.

### 4.4 TDD sequence

Work one resource at a time: **Programs → Courses → Academic Terms → Students.** Programs is your template; once it's green, the others are variations. Students goes last because it needs programs to exist for `program_id`. Create one test class per resource — `php artisan make:test --phpunit CourseApiTest` — under `tests/Feature`.

**The Programs tests are already written:** `tests/Feature/ProgramApiTest.php` (15 test methods, expanding to 19 cases because the `401` test runs once per route). They're red now, and they are both the spec for the Program endpoints and the template for the other three — read them before you build, and copy their shape (including the two private helpers, `actingAsAdministrator()` and `assertValidationFailed()`) when you write the Courses, Academic Terms and Students tests yourself.

For each resource, the tests cover these, in this order. Each should be red for a specific, understood reason before you make it green (typically: `404` because the route doesn't exist → `401`s or empty `200`s → wrong shape → green):

1. **Every route without a token → `401`** — one data-provider test over `index`, `show`, `store`, `update` and `destroy`, which also proves the whole `apiResource` sits inside `auth:sanctum`.
2. **List with a token** returns the created records under `data`, plus `success: true` and the `message`.
3. **List is paginated:** with 16 records, page 1 holds 15 and `meta.total` is 16 (see the pagination note in 4.1 — check that your factory can make that many).
4. **Show** returns the record in the envelope; an **unknown id → `404`**. (Until the route exists this test passes for the wrong reason — the route itself is what's missing. It only becomes meaningful once the route is registered.)
5. **Store with valid data → `201`**, the envelope, the new record in `data`, and the row in the database. **Store with an optional field omitted → `201`.**
6. **Store with missing required fields → `422`** in the error shape (`success: false`, `message: "Validation failed."`, an `errors` entry per missing field), and nothing persisted.
7. **Store with a duplicate unique value → `422`** (not `500`), and **with an invalid enum value** (e.g. `status: archived`) **→ `422`**.
8. **Update with valid data → `200`** and the database changed. **Update resending its own unique value → `200`** (the ignore-yourself case). **Update with another record's unique value → `422`**, row unchanged.
9. **Destroy an unreferenced record → `204`** and the row is gone.
10. **Destroy a referenced record → `409`** and the row is still there.

Extra cases spec §16 names or implies:

- **Students:** invalid email → `422`; nonexistent `program_id` → `422`; duplicate `student_number` → `422`.
- **Academic Terms:** `end_date` before `start_date` → `422`; duplicate `(academic_year, term)` → `422`.
- **Courses:** `units` outside your range → `422`.

**Illustrative examples — the least obvious ones, taken from `ProgramApiTest`.** Each test builds its own data; nothing is shared between tests.

A success response is asserted as the envelope plus the data, and the database row:

```php
public function test_store_with_valid_data_returns_201_and_creates_the_program(): void
{
    $this->actingAsAdministrator();

    $response = $this->postJson('/api/v1/programs', [
        'code' => 'BSCS',
        'name' => 'Bachelor of Science in Computer Science',
        'status' => 'inactive',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Program created successfully.')
        ->assertJsonPath('data.code', 'BSCS');
    $this->assertDatabaseHas('programs', ['code' => 'BSCS', 'status' => 'inactive']);
}
```

Every `422` shares the same shape, so one private helper asserts it, and each validation test calls it with the fields that should have errors. The `message` is asserted exactly; the per-field messages aren't, because for the required/unique/in rules they're Laravel's defaults:

```php
private function assertValidationFailed(TestResponse $response, array $fields): void
{
    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Validation failed.')
        ->assertJsonValidationErrors($fields);
}

// used as:  $this->assertValidationFailed($response, ['code', 'name']);
```

The ignore-yourself case, which catches a `unique` rule missing its `->ignore(...)`:

```php
public function test_update_resending_its_own_code_returns_200(): void
{
    $this->actingAsAdministrator();
    $program = Program::factory()->create(['code' => 'BSCS']);

    $response = $this->putJson("/api/v1/programs/{$program->id}", [
        'code' => 'BSCS',
        'name' => 'Renamed Program',
    ]);

    $response->assertOk()->assertJsonPath('data.name', 'Renamed Program');
}
```

And a delete that must be refused, with the row still present afterward:

```php
public function test_destroy_returns_409_when_the_program_has_students(): void
{
    $this->actingAsAdministrator();
    $program = Program::factory()->create();
    Student::factory()->create(['program_id' => $program->id]);

    $this->deleteJson("/api/v1/programs/{$program->id}")->assertConflict();

    $this->assertModelExists($program);
}
```

Write the tests for the other three resources yourself, following the same shape. Note the difference from Phase 2: the duplicate-`student_number` test there proves the **database constraint** (it expects a `QueryException`); the one here proves the **API** turns a duplicate into a clean `422`. You want both — and the Phase 2 one is still unchecked.

### 4.5 Easy-to-miss traps

- **`authorize()` returns `false` by default** in the generated request — change it to `true` (see 4.0).
- **`unique` on update must ignore the current record** — otherwise every update that resends an unchanged code fails.
- **Enum and range columns don't validate for you** — `status`, `term`, `year_level` and `units` need explicit rules, or bad input becomes a database error.
- **Routes at the wrong level** — `apiResource` inside your `auth` prefix group produces `/api/v1/auth/programs`. Check with `php artisan route:list --path=v1`.
- **Postman/clients should send `Accept: application/json`.** Your `bootstrap/app.php` forces JSON errors for `api/*`, but the header is the safe habit and the tests' `getJson`/`postJson` helpers already send it.
- **The `422` shape isn't automatic.** Laravel's default validation error has no `success` key, so every validation test fails on `success` until the `ValidationException` renderer exists in `bootstrap/app.php`. Build it first (see 4.2).
- **`success` must be a real boolean.** `'success' => true`, not `'true'`; the tests compare the JSON type.
- **The `204` has no envelope.** Don't `additional()` onto `response()->noContent()`; a `204` carries no body.
- **Unique-limited factories break bulk data.** `fake()->unique()->randomElement([...])` runs out after the list is exhausted (12 programs) and throws, even when you override the field. For pagination tests needing 16+ rows, insert them directly or check how many distinct values your factory can produce.
- **A unique-limited factory can bite you *indirectly*.** `Student::factory()->count(16)->create()` failed for the same reason as the point above, but the exhausted factory was `ProgramFactory`, not `StudentFactory` — every student's default `program_id` spawns a *new* `Program::factory()`, and 16 of those exceeds `ProgramFactory`'s pool of 12. Fix: create one shared `Program` first and pass its id explicitly (`Student::factory()->count(16)->create(['program_id' => $program->id])`). Watch for this any time one factory's default relationship points at another factory with a capped/unique pool — Phase 5's `CourseOffering` (which defaults `course_id`, `academic_term_id` and `instructor_id` to their own factories) is a likely repeat.
- **`fake()->unique()->phoneNumber()` collides faster than you'd expect.** Its few locale-specific formats ran out well before Faker's own 10,000-retry limit once a test file created a few dozen students. `fake()->unique()->numerify('09#########')` (a fixed digit template, same idea as `student_number`) sidesteps the problem entirely — prefer `numerify()` over a semantic Faker method whenever a field just needs to be unique, not necessarily "look real".

### 4.6 Retrofit the Phase 3 endpoints to the envelope

Once Programs is fully green, bring `login` and `me` in line with 4.1 so the whole API is consistent. `logout` stays `204` with no body. This changes existing behaviour, so it changes two Phase 3 tests too:

| Endpoint | New body | Suggested `message` |
|---|---|---|
| `POST /api/v1/auth/login` (success) | `{"success": true, "message": ..., "data": {"token": "..."}}` | `Login successful.` |
| `GET /api/v1/auth/me` | `{"success": true, "message": ..., "data": {<the user>}}` | `Current user retrieved successfully.` |

- `AuthenticationTest` #2 asserts the token at the top level (`assertJsonStructure(['token'])`); it becomes `data.token`, and should also assert `success` and `message`.
- `AuthenticationTest` #4 asserts `id`, `email` and the missing `password` at the top level; those paths become `data.id`, `data.email` and `data.password` (still asserted *missing*). Keep the `remember_token` assertion too.
- `me()` currently returns the `User` model itself. To wrap it, either pass it through a small resource with `additional()`, or build the array by hand — but the model's `#[Hidden]` only protects you if you serialize the model (or a resource that includes only the fields you list), so keep test #4's missing-`password` assertion as your safety net.
- The failed-login `422` needs no change: the global renderer from 4.1 already reshapes it, and test #3 only asserts that `message` and `errors` exist (you can tighten it to assert `success: false` and `"Validation failed."`).

### Phase 4 checklist

- [x] Global `ValidationException` renderer in `bootstrap/app.php` returns the spec §9 error shape (`success: false`, `message: "Validation failed."`, `errors`) with status `422` for `api/*` requests
- [x] `apiResource` routes for `programs`, `courses`, `academic-terms`, `students` inside `v1` + `auth:sanctum` (`php artisan route:list --path=v1` shows five routes each, all behind `auth:sanctum`)
- [x] Each resource has a store request, an update request, and an API Resource class
- [x] Every successful response except `204` uses the envelope (`success: true`, `message`, `data`); collections keep `links` and `meta` beside it
- [x] Every route of every resource without a token → `401`
- [x] **Programs:** `ProgramApiTest` passes in full — 19 cases
- [x] **Courses:** `CourseApiTest` passes in full — 27 cases, incl. `units` out of range → `422`
- [x] **Academic Terms:** `AcademicTermApiTest` passes in full — 33 cases, incl. `end_date` before `start_date` `422`, duplicate `(academic_year, term)` `422`, and the custom `ConsecutiveAcademicYear` rule (14 unit cases of its own)
- [x] **Students:** `StudentApiTest` passes in full — 37 cases, incl. invalid email, nonexistent `program_id`, duplicate `student_number`/`email`/`contact_number` (each `422`)
- [x] Deleting a still-referenced record returns `409` — never `500` — for all four resources
- [x] Collection endpoints are paginated (no unbounded `->get()`), with a test that proves it
- [ ] Phase 3 retrofit done (4.6): `login` and `me` use the envelope, and `AuthenticationTest` #2 and #4 are updated — **not done, see follow-ups below**
- [x] `php artisan test` green; `vendor/bin/pint --dirty --format agent` clean on every file touched this phase

**Verified 2026-09-23:** `php artisan test` passes (145 tests / 550 assertions) — the previous 108 (Phases 1–3 + Programs/Courses/Academic Terms) plus 37 new `StudentApiTest` cases. `php artisan route:list --path=v1` shows 18 routes: 3 auth + 5 each for `programs`, `courses`, `academic-terms`, `students`, all four resource groups behind `auth:sanctum`. The checkpoint ("CRUD requests work with validation") is met for all four resources. Phase 4 is done — commit this work when you're ready.

> **Follow-ups (2026-09-23)** — none of these block Phase 5:
> 1. **4.6 (Phase 3 envelope retrofit) is still outstanding.** `login` and `me` still return their original flat bodies, not the spec §9 envelope every other endpoint now uses. Low urgency — those two endpoints work correctly, they're just inconsistent in shape with the rest of the API. Worth doing before Phase 8 (Documentation), where a single documented response format matters more.
> 2. **Known bug, deliberately not fixed:** `ProgramController::store` and `CourseController::store` return `"status": null` in the response body when `status` is omitted from the request, even though the database correctly stores the column's default (`active`). Confirmed by manual testing (login → `POST` without `status` → compare the create response against a follow-up `GET`). The fix is the same one-liner already used in `AcademicTermController`/`StudentController`: `->refresh()` after `->create()`. Not applied to Programs/Courses this session by explicit choice — do it with a test (assert `data.status` on an omitted-status create) whenever you're ready.
> 3. **`students` schema change needs a manual step.** `email` and `contact_number` are now `NOT NULL` + `UNIQUE` (new migration `add_constraints_to_students_table`). Your dev SQLite database still has the old schema until you run `php artisan migrate:fresh --seed` yourself.
> 4. The two unchecked Phase 2 constraint tests (`student_number` uniqueness, duplicate enrollment) are still open — see the Phase 2 note above. Still not a blocker, but circle back before Phase 9.

Come back once you're ready and I'll expand **Phase 5 — Academic Transactions** — Course Offerings, Enrollments, Grades and the Academic Record, where the cross-table rules (capacity, duplicate enrollment, grade ranges) live.
