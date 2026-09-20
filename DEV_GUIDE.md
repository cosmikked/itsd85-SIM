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
- [ ] **Phase 4 — Core Resources**: Programs, Students, Courses, Academic Terms (CRUD + validation) *(expanded below)*
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

> **Non-blocking follow-ups (2026-09-20)** — three small things worth tidying, none of which stop Phase 4:
> 1. **Route names.** `->name('auth.')` on a route *group* only sets a name **prefix**; each route still needs its own `->name('login')` / `->name('logout')` / `->name('me')`. Right now `route:list` shows all three as plain `auth.`, so `route('auth.login')` can't tell them apart.
> 2. **`me()` returns only `id` and `email`.** 3.3 says to return the user (`$request->user()`). Returning the model gives clients `name` and `role` (Phase 7 will need `role`), and it makes test #4's `assertJsonMissingPath('password')` prove something — with a hand-picked array that assertion can never fail, so it no longer shows `#[Hidden]` doing its job.
> 3. **Formatting.** Run `vendor/bin/pint --dirty --format agent`; `AuthController.php` and `routes/api.php` still have trailing spaces and stray blank lines.

---

## Phase 4 — Core Resources *(expanded)*

**Checkpoint (spec §19):** CRUD requests work with validation.

**Required endpoints (spec §8.1)** — four resources, all with the same shape:

| Resource | Collection | Single record |
|---|---|---|
| Programs | `GET`, `POST /api/v1/programs` | `GET`, `PUT`/`PATCH`, `DELETE /api/v1/programs/{id}` |
| Courses | `GET`, `POST /api/v1/courses` | `GET`, `PUT`/`PATCH`, `DELETE /api/v1/courses/{id}` |
| Academic Terms | `GET`, `POST /api/v1/academic-terms` | `GET`, `PUT`/`PATCH`, `DELETE /api/v1/academic-terms/{id}` |
| Students | `GET`, `POST /api/v1/students` | `GET`, `PUT`/`PATCH`, `DELETE /api/v1/students/{id}` |

**Out of scope for this phase** (so you don't build them early): search / filter / sort / `per_page` (Phase 6), role-based rules (Phase 7), nested routes like `/students/{id}/enrollments` (Phase 5). Every endpoint here simply requires a valid token.

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

### 4.1 Four decisions to make first

These are choices the spec leaves open. My recommendation for each is below — make the call, then keep it consistent across all four resources.

| Decision | Recommendation | Why |
|---|---|---|
| **Response shape** | API Resources with the default `data` wrapper. Leave the spec §9 `success` / `message` envelope for **Phase 6** ("consistent errors"). | Phase 6 is where the spec asks for consistent responses; designing the envelope now means designing it twice. Login and `me` staying flat is fine. |
| **Deleting a record that's still referenced** | Return `409 Conflict` and don't delete. (Details below.) | Otherwise the database refuses and you return a `500`. |
| **Authentication in tests** | `Sanctum::actingAs(User::factory()->administrator()->create())` | An administrator may do everything (spec §12.2), so Phase 7's rules won't break your Phase 4 tests. |
| **Collection endpoints** | `ProgramResource::collection(Program::paginate())` from the start. | Spec §11: collections must not be unbounded. Retrofitting pagination later changes the JSON shape and every list test. |

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

Controller method shapes:

- `index` — `paginate()` the model, return `Resource::collection(...)`.
- `store` — `Model::create($request->validated())`, return the resource (`201`). Mass assignment is safe here because your models declare `#[Fillable([...])]`; never pass `$request->all()`.
- `show` — `return new Resource($model)`.
- `update` — `$model->update($request->validated())`, return the resource (`200`).
- `destroy` — dependants exist → `409` with a clear message; otherwise delete and return `response()->noContent()` (`204`).

### 4.3 Validation rules per resource

Derived from your migrations and spec §10. The database enums and `unsignedTinyInteger` columns don't give you a friendly error — a bad value throws a database exception (a `500`) unless a rule catches it first. **Your rules are what turn "bad input" into a `422`.**

| Resource | Field → rule ideas |
|---|---|
| **Programs** | `code`: required, string, unique. `name`: required, string, max 255. `description`: nullable string. `status`: optional, `in:active,inactive` (DB default is `active`). |
| **Courses** | `course_code`: required, string, unique. `course_title`: required, string, max 255. `description`: nullable string. `units`: required, integer, **with a range you choose and document** — the column is `unsignedTinyInteger` (0–255), so the database alone won't stop `units: 200`; your factory uses 3–5. `status`: `in:active,inactive`. |
| **Academic Terms** | `academic_year`: required, string (decide a format such as `2026-2027`; a `regex` rule can enforce it). `term`: required, `in:First Semester,Second Semester,MidYear` (exactly the migration's enum). `start_date`: required date. `end_date`: required date, `after:start_date`. `status`: `in:active,inactive`. The `(academic_year, term)` pair is unique in the database — add a matching validation rule (`Rule::unique(...)->where(...)`) so a duplicate is a `422`, not a `500`. |
| **Students** | `student_number`: required, string, unique. `first_name`, `last_name`: required, string, max 255. `middle_name`, `suffix`: nullable string. `birth_date`: required date, `before:today`. `email`: nullable `email` (spec §10: "valid email format when supplied"; the column isn't unique — leave it unless you decide otherwise). `contact_number`, `address`: nullable string. `program_id`: required, `exists:programs,id` (spec §16 "invalid references"). `year_level`: required integer — your factory only generates 1–4, so **1–4 is the range your seed data already proves**; widen it only if you widen the factory too. `status`: `in:regular,irregular,extendee`. |

Keep each field's allowed values in one place (a constant on the model, for example) so the store and update requests can't drift apart.

### 4.4 TDD sequence

Work one resource at a time: **Programs → Courses → Academic Terms → Students.** Programs is your template; once it's green, the others are variations. Students goes last because it needs programs to exist for `program_id`. Create one test class per resource — `php artisan make:test --phpunit ProgramApiTest` — under `tests/Feature`.

For each resource, write these in order. Each should be red for a specific, understood reason before you make it green (typically: `404` because the route doesn't exist → `500` "Class ...Controller does not exist" → wrong status/shape → green):

1. **List without a token → `401`.**
2. **List with a token** returns the created records under `data`.
3. **Show** returns the record; an **unknown id → `404`**.
4. **Store with valid data → `201`**, the new record in `data`, and the row in the database.
5. **Store with missing required fields → `422`** with an error for each missing field, and nothing persisted.
6. **Store with a duplicate unique value → `422`** (not `500`).
7. **Update with valid data → `200`** and the database changed. **Update resending its own unique value → `200`** (the ignore-yourself case).
8. **Destroy an unreferenced record → `204`** and the row is gone.
9. **Destroy a referenced record → `409`** and the row is still there.

Extra cases spec §16 names or implies:

- **Students:** invalid email → `422`; nonexistent `program_id` → `422`; duplicate `student_number` → `422`.
- **Academic Terms:** `end_date` before `start_date` → `422`; duplicate `(academic_year, term)` → `422`.
- **Courses:** `units` outside your range → `422`.

**Illustrative examples — the three least obvious ones.** Each test builds its own data; nothing is shared between tests.

```php
// tests/Feature/ProgramApiTest.php
public function test_store_with_valid_data_returns_201_and_creates_program(): void
{
    Sanctum::actingAs(User::factory()->administrator()->create());

    $response = $this->postJson('/api/v1/programs', [
        'code' => 'BSCS',
        'name' => 'BS Computer Science',
    ]);

    $response->assertCreated()->assertJsonPath('data.code', 'BSCS');
    $this->assertDatabaseHas('programs', ['code' => 'BSCS']);
}

public function test_update_resending_its_own_code_returns_200(): void
{
    Sanctum::actingAs(User::factory()->administrator()->create());
    $program = Program::factory()->create(['code' => 'BSCS']);

    $response = $this->putJson("/api/v1/programs/{$program->id}", [
        'code' => 'BSCS',
        'name' => 'Renamed Program',
    ]);

    $response->assertOk()->assertJsonPath('data.name', 'Renamed Program');
}

public function test_destroy_returns_409_when_program_has_students(): void
{
    Sanctum::actingAs(User::factory()->administrator()->create());
    $program = Program::factory()->create();
    Student::factory()->create(['program_id' => $program->id]);

    $this->deleteJson("/api/v1/programs/{$program->id}")->assertConflict();

    $this->assertModelExists($program);
}
```

Write the rest yourself, following the same shape. Note the difference from Phase 2: the duplicate-`student_number` test there proves the **database constraint** (it expects a `QueryException`); the one here proves the **API** turns a duplicate into a clean `422`. You want both — and the Phase 2 one is still unchecked.

### 4.5 Easy-to-miss traps

- **`authorize()` returns `false` by default** in the generated request — change it to `true` (see 4.0).
- **`unique` on update must ignore the current record** — otherwise every update that resends an unchanged code fails.
- **Enum and range columns don't validate for you** — `status`, `term`, `year_level` and `units` need explicit rules, or bad input becomes a database error.
- **Routes at the wrong level** — `apiResource` inside your `auth` prefix group produces `/api/v1/auth/programs`. Check with `php artisan route:list --path=v1`.
- **Postman/clients should send `Accept: application/json`.** Your `bootstrap/app.php` forces JSON errors for `api/*`, but the header is the safe habit and the tests' `getJson`/`postJson` helpers already send it.

### Phase 4 checklist

- [ ] `apiResource` routes for `programs`, `courses`, `academic-terms`, `students` inside `v1` + `auth:sanctum` (`php artisan route:list --path=v1` shows five routes each, all behind `auth:sanctum`)
- [ ] Each resource has a store request, an update request, and an API Resource class
- [ ] Every collection endpoint without a token → `401`
- [ ] **Programs:** list, show, unknown id → `404`, store `201`, store missing fields `422`, duplicate `code` `422`, update `200`, update with own `code` `200`, destroy `204`, destroy referenced `409` — tests written and passing
- [ ] **Courses:** the same set, plus `units` out of range → `422`
- [ ] **Academic Terms:** the same set, plus `end_date` before `start_date` `422` and duplicate `(academic_year, term)` `422`
- [ ] **Students:** the same set, plus invalid email, nonexistent `program_id` and duplicate `student_number` (each `422`)
- [ ] Deleting a still-referenced record returns `409` — never `500` — for all four resources
- [ ] Collection endpoints are paginated (no unbounded `->get()`)
- [ ] `php artisan test` green; `vendor/bin/pint --dirty --format agent` clean

Work through 4.0–4.5 yourself, test-first, one resource at a time. Come back once the checklist is checked and I'll expand **Phase 5 — Academic Transactions** — Course Offerings, Enrollments, Grades and the Academic Record, where the cross-table rules (capacity, duplicate enrollment, grade ranges) live. If you have a spare moment before then, the two unchecked Phase 2 constraint tests (`student_number`, duplicate enrollment) are quick wins.
