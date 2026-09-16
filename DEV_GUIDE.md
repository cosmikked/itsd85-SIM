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

- [x] **Phase 1 — Project Setup**: framework, repo, environment, database connection *(expanded below)*
- [ ] **Phase 2 — Database Design**: ERD, migrations, constraints, relationships, seeders
- [ ] **Phase 3 — Authentication**: login, password hashing, protected routes, current user
- [ ] **Phase 4 — Core Resources**: Programs, Students, Courses, Academic Terms (CRUD + validation)
- [ ] **Phase 5 — Academic Transactions**: Course Offerings, Enrollments, Grades, Academic Record
- [ ] **Phase 6 — Advanced API Features**: search, filtering, sorting, pagination, consistent errors
- [ ] **Phase 7 — Authorization**: role-based + object-level access rules
- [ ] **Phase 8 — Documentation**: README, Scramble/OpenAPI, ERD, Postman collection
- [ ] **Phase 9 — Testing**: full positive/negative/validation/security pass, automated suite
- [ ] **Phase 10 — Final Demonstration**: local run-through of all 20 acceptance items (spec §22)

---

## Phase 1 — Project Setup *(expanded)*

**Checkpoint (spec §19):** API server starts and database connection succeeds.

### 1.1 Confirm the base app boots

Already true in this repo (Laravel 13 skeleton, `.env` configured with `DB_CONNECTION=sqlite`). Verify it yourself:

```bash
php artisan about
```

Confirm `Environment`, `Database` (sqlite, file present at `database/database.sqlite` — create it if missing with `touch database/database.sqlite` or the PowerShell equivalent), and no errors.

### 1.2 Install Sanctum (auth) and Scramble (API docs)

```bash
composer require laravel/sanctum dedoc/scramble
php artisan install:api
```

`install:api` publishes Sanctum's migration, adds the `api` route group, and creates `routes/api.php` with `Route::middleware('auth:sanctum')` scaffolding — this is where every protected endpoint in later phases will live.

Scramble needs no publish step by default; once installed, visiting `/docs/api` renders OpenAPI docs generated from your routes/FormRequests/Resources as you build them in later phases.

### 1.3 Run the initial migration

```bash
php artisan migrate
```

This creates `users`, `cache`, `jobs`, and Sanctum's `personal_access_tokens` tables. Confirm no errors.

### 1.4 Configure PHPUnit for a clean, fast test DB

Laravel's default `phpunit.xml` should already set the testing environment to use an in-memory SQLite database (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) — check this in `phpunit.xml`. This matters for TDD: every test run gets a fresh, isolated database, and in-memory means it's fast enough to run constantly.

Verify the test suite currently runs (it'll be nearly empty, that's fine):

```bash
php artisan test
```

### 1.5 Write your first test — prove the harness works

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

- [x] `php artisan about` shows no errors, SQLite connected
- [x] Sanctum installed, `routes/api.php` exists with `auth:sanctum` group
- [x] Scramble installed, `/docs/api` renders (even if empty)
- [x] `php artisan migrate` succeeds
- [x] `phpunit.xml` confirmed to use in-memory SQLite for tests
- [x] `ApplicationBootTest` written and passing
- [x] `php artisan serve` starts without error (confirmed via `php artisan about`, which boots the full framework)

**Phase 1 complete.** Note: `.env` and `database/database.sqlite` are gitignored (the latter via `database/.gitignore`) and were created locally — you'll need to repeat steps 1.1–1.3 (`cp .env.example .env`, `php artisan key:generate`, create the SQLite file, `php artisan migrate`) on any machine that clones this repo fresh, including your own if you reclone. This is expected and matches spec §14 (reproducibility via `.env.example`, not a committed `.env`).

Next: **Phase 2 — Database Design** — ERD approach, migration order, and TDD sequence for constraints (unique `student_number`, duplicate-enrollment prevention, etc.). Ask when you're ready to start it.
