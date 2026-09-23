# Handoff

For any agent (Antigravity or otherwise) picking up this project. **Read this file before touching anything.**

*Written 2026-09-23, at the point Phase 4 (Core Resources) is complete and Phase 5 (Academic Transactions) is about to start.*

---

## The one rule that overrides everything else

This is a student lab project (see `ACTIVITY_INSTRUCTIONS.md`). The developer is learning Laravel and wants to write the implementation themselves; the assessment explicitly requires them to be able to explain and modify every part of the code, including AI-assisted portions.

**Do not execute implementation steps on the developer's behalf** — no running `composer require`/`artisan make:*`/migrations, no writing controllers/models/tests/policies, no committing or pushing, unless the developer explicitly asks you to do that specific thing in that moment. Default mode is: explain the step, show the command or a short illustrative snippet, and let the developer run/write it themselves. This applies whether or not `DEV_GUIDE.md` is in play.

It's fine to: read/search the codebase, answer questions, review code the developer wrote, point out bugs, and run read-only commands (`php artisan route:list`, `php artisan test`, `php artisan about`, etc.) to check their work.

### Git — Developer Commits and Pushes

Do not run `git commit` or `git push` (or anything that publishes changes, e.g. opening a PR) unless the developer explicitly asks for that specific commit/push in the moment. The developer wants to review and commit their own work — leave changes staged or as unstaged edits and tell them what changed instead of committing it yourself.

This same wording lives in `CLAUDE.md` and `AGENTS.md` — it's duplicated deliberately so it survives regardless of which convention file you read.

---

## Where things live

| File | What it is |
|---|---|
| `ACTIVITY_INSTRUCTIONS.md` | The grading spec. Read-only — don't edit it. |
| `DEV_GUIDE.md` | **Read this next.** The authoritative phase-by-phase log: what's built, why, the TDD sequence, decisions made, checklists. This is where you pick up the story. |
| `FUNCTIONAL_REQUIREMENTS.md` | Per-endpoint plan (roles, validation, success/error shape) for every resource, including the ones not built yet. Kept current as of this handoff. |
| `SCRATCHPAD.md` | An open design discussion (custom exception classes for domain errors). Explicitly says so at the top: **nothing in it is implemented**. |
| `AGENTS.md` / `CLAUDE.md` | Tooling conventions (Laravel Boost) plus the pedagogical rule above. |

---

## Current state

- **Phases 1–4 are complete and committed.** `git log` runs through `feat: students api endpoint`. Nothing from this work is sitting uncommitted.
- **Phase 5 (Course Offerings, Enrollments, Grades, Academic Record) has not been started.** `DEV_GUIDE.md`'s Phase 5 section is still a stub — it needs to be expanded (with the developer, following the same pattern as Phases 3–4) before any code gets written.


## Known open items (carried over, don't lose track of these)

1. **Phase 3's response envelope retrofit (DEV_GUIDE 4.6) is still outstanding.** `POST /auth/login` and `GET /auth/me` still return their original flat bodies, not the `success`/`message`/`data` envelope every other endpoint now uses. Low urgency, but inconsistent.
2.  **Two Phase 2 constraint tests are still unwritten**: `student_number` uniqueness and duplicate-enrollment prevention. The database constraints themselves exist; only the dedicated tests proving them are missing. Not a blocker, but circle back before Phase 9 (Testing).

---

## Conventions established in Phases 3–4

Keep these consistent going into Phase 5 — they're not written down anywhere else as a single list:

- **Response envelope**, built once in `bootstrap/app.php`:
  - Success (except `204`): `{"success": true, "message": "...", "data": {...}}`, built with an API Resource plus `->additional([...])`.
  - Validation error (`422`): `{"success": false, "message": "Validation failed.", "errors": {field: [...]}}`, via a global `ValidationException` renderer.
  - `204 No Content` (delete) carries no body — never wrap it in the envelope.
- **One Form Request pair per model** (`Store{Model}Request`, `Update{Model}Request`) plus **one API Resource**. The generated `make:request` stub defaults `authorize()` to `false` — change it to `true`.
- **Update requests are partial-friendly**: every field is `sometimes` (+ `required` where Store has plain `required`). Where a rule compares two fields (e.g. Academic Terms' date order or its year+term pair), a field that wasn't sent falls back to the record's stored value.
- **Uniqueness on update** always uses `Rule::unique(...)->ignore($model)` so resending an unchanged value doesn't fail.
- **Deleting a referenced record returns `409`**, checked at the app layer (`$model->relation()->exists()`) before attempting the delete — never let the database's own foreign-key constraint surface as a raw `500`.
- **A validation rule worth naming gets its own class.** `app/Rules/ConsecutiveAcademicYear.php` is the example — logic real enough to have its own unit test, not just an inline closure.
- **TDD, strictly**: the test file is written and shown red (and reviewed by the developer) *before* any implementation exists. Each resource follows the same order: routes → global error shape (if new) → resource/requests → controller methods, one at a time, re-running the relevant test after each step.
- **`vendor/bin/pint --dirty --format agent`** after every PHP change, before considering it done.
- Two small test helpers, `actingAsAdministrator()` and `assertValidationFailed()`, are duplicated in every `*ApiTest.php` class by convention (not extracted to a shared trait) — match that pattern in new test classes rather than refactoring it away mid-project.
- **Factories with a capped/unique pool bite you indirectly.** `ProgramFactory` only has 12 fixed programs; any factory whose default relationship points at `Program::factory()` (or another capped factory) can silently exhaust it in bulk-creation tests (e.g. a 16-record pagination test). Watch for this in Phase 5 — `CourseOffering` defaults `course_id`, `academic_term_id` and `instructor_id` to their own factories.

## How this session actually worked (a model to follow)

"Teach, don't do" is abstract without an example. What actually happened here, resource by resource:
1. Plan mode / explicit developer approval before building anything for a given resource.
2. Tests written first, run, shown red, for an *understood* reason (route missing, class missing, etc.) — never just "run everything and see."
3. Implementation (requests → resource → controller → route) written only after the test file itself was reviewed.
4. Tests re-run after each piece, not just at the end.
5. One resource at a time, with a checkpoint back to the developer between resources — not all four built silently in one pass.

Follow the same rhythm for Phase 5.
