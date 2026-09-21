# SCRATCHPAD

Working notes for the SIM backend. **Nothing in this file is implemented in the codebase** — the code blocks are sketches to think with, not files that exist.

*Written 2026-09-21.*

## Contents

1. [Exception classes vs. global registration in `bootstrap/app.php`](#1-exception-classes-vs-global-registration-in-bootstrapappphp) — the discussion
2. [Sketch: `RecordInUseException`](#2-sketch-recordinuseexception) — a reusable 409 for "can't delete, still referenced"
3. [Open decisions](#3-open-decisions)

---

## 1. Exception classes vs. global registration in `bootstrap/app.php`

*Question: could we use Laravel's Exception classes for the error responses, when should that be used vs. the global registration in `bootstrap/app.php`, and how do we keep the implementation clean and not scattered?*

There are two approaches, and they don't compete. They handle different kinds of errors.

### What the "Exception class" approach is
You write your own exception class. Laravel automatically asks any thrown exception "do you have a `render()` method?" and uses its answer as the response. Verified in the installed framework: `Handler.php:709` calls `$e->render($request)` **before** it looks at the callbacks registered in `bootstrap/app.php` (line 723). A self-rendering exception wins.

A tiny example, using the delete rule:

```php
// app/Exceptions/ProgramHasStudentsException.php  (illustrative)
class ProgramHasStudentsException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Program cannot be deleted because it still has students.',
            'errors'  => [],
        ], 409);
    }
}

// in the controller
throw new ProgramHasStudentsException;
```

### The catch with that version
The JSON shape (`success`, `message`, `errors`) is now written in this class **and** in `bootstrap/app.php`. With ten such exceptions in Phase 5, the shape is in ten places — exactly the "scattered" problem to avoid.

### A cleaner variant (recommended)
Make the exception carry only its **meaning** — a status and a message — and leave the JSON shape to the one place that already builds it. Symfony (which Laravel uses) provides exception classes for statuses. `ConflictHttpException` is the 409 one, and it exists in this project's `vendor`:

```php
class ProgramHasStudentsException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('Program cannot be deleted because it still has students.');
    }
}
```

There's no `render()` method here. Because it *is* an `HttpException`, the existing `HttpException` callback in `bootstrap/app.php` already turns it into the envelope with status 409 and this message. The shape lives in **one place**, and the exception class only says what went wrong.

Bonus: Laravel's `internalDontReport` list (`Handler.php:174`) includes `HttpException`, so expected 4xx errors like this **aren't written to `laravel.log`**. The log stays for genuine bugs.

### When to use which
| Situation | Use | Why |
|---|---|---|
| Errors thrown by **Laravel or packages**: validation, unauthenticated, missing model, unknown route, unexpected crash | **Global callbacks in `bootstrap/app.php`** | You can't add methods to Laravel's own classes, so this is the only place to shape them |
| A one-off error in one controller, message used once | `abort(409, '...')` | Simplest, nothing new to maintain |
| A **business rule** with a name in your domain (program has students, offering is full, duplicate enrollment), or one used in more than one place | **Custom exception class** extending the right Symfony HTTP exception | The rule's wording and status live in one class, controllers stay readable, and the rule can be tested by name |
| A rule that needs a *different* response shape (extra fields in the body) | Custom exception **with its own `render()`** | The only case where the class should build the JSON itself |

So it's a split:
- **`bootstrap/app.php`** owns the *shape*, and handles everything the framework throws.
- **Exception classes** own the *meaning* of your business rules.

### What "clean, not scattered" looks like here
1. **One envelope builder.** The `$error` closure stays the single place that writes `success` / `message` / `errors`.
2. **Framework exceptions in `bootstrap/app.php`:** validation, authentication, not found (fixed message), `HttpException`, and a `Throwable` catch-all.
3. **Domain rules as small exception classes** that extend `HttpException` subclasses. They add no JSON.
4. **Controllers only `throw`.** They never build error responses.

For Phase 4 specifically, the four delete rules have the same shape: "X can't be deleted because it still has Y". One reusable class could replace four — for example a `RecordInUseException` that takes the record name and what depends on it, and produces a 409 with a message like `Program cannot be deleted because it still has students.`

### Two things to decide
1. **Folder:** there is no `app/Exceptions` folder in this project (only `Http`, `Models`, `Providers`). Laravel's default location for these classes is `app/Exceptions`, but the project rules say not to create new base folders without approval. It is a normal Laravel convention, but it needs an OK first.
2. **Timing:** with a single `abort(409, ...)` in `destroy` today, a custom class is more structure than needed right now. It starts paying off once the same rule appears for Courses, Academic Terms and Students in this phase, and again with Phase 5's rules (duplicate enrollment, full offerings, invalid grades).

---

## 2. Sketch: `RecordInUseException`

**Goal:** one class that replaces the four copies of "X can't be deleted because it still has Y", so each `destroy` method reads as a rule and never builds an error response.

### The class

```php
<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Thrown when a record cannot be deleted because other records still reference it.
 */
class RecordInUseException extends ConflictHttpException
{
    /**
     * @param  string  $record  The record being deleted, e.g. "Program".
     * @param  string  $dependants  What still references it, e.g. "students".
     */
    public function __construct(string $record, string $dependants)
    {
        parent::__construct("{$record} cannot be deleted because it still has {$dependants}.");
    }
}
```

Why this shape:
- Extends `ConflictHttpException` → status **409** for free, and it *is* an `HttpException`, so the existing `HttpException` callback in `bootstrap/app.php` produces the envelope. **No `render()`, no JSON in the class.**
- Only the message wording lives here, built from two parameters, so all four resources say the same thing the same way.
- Not written to `laravel.log` (HttpExceptions are in `internalDontReport`).

### How the four `destroy` methods would use it

```php
// ProgramController
if ($program->students()->exists()) {
    throw new RecordInUseException('Program', 'students');
}

// CourseController
if ($course->courseOfferings()->exists()) {
    throw new RecordInUseException('Course', 'course offerings');
}

// AcademicTermController
if ($academicTerm->courseOfferings()->exists()) {
    throw new RecordInUseException('Academic term', 'course offerings');
}

// StudentController
if ($student->enrollments()->exists()) {
    throw new RecordInUseException('Student', 'enrollments');
}
```

Laravel's `throw_if()` helper makes each one a single statement, if that reads better:

```php
throw_if(
    $program->students()->exists(),
    new RecordInUseException('Program', 'students'),
);
```

### What the client receives

```json
{
  "success": false,
  "message": "Program cannot be deleted because it still has students.",
  "errors": []
}
```
with HTTP status `409`. This is byte-for-byte what `abort(409, '...')` produces today, so the existing `test_destroy_returns_409_when_the_program_has_students` needs no change. It could optionally also assert the message text.

### Flow, end to end
```
DELETE /api/v1/programs/5
  → auth:sanctum ok
  → ProgramController::destroy(Program $program)
      → students()->exists()  → true
      → throw new RecordInUseException('Program', 'students')     (a ConflictHttpException, i.e. an HttpException)
  → Handler::render(): the exception has no render() method, so callbacks run
      → ValidationException callback?     no
      → AuthenticationException callback? no
      → HttpException callback?           yes → $error($e->getMessage(), 409)
  → 409 + {success:false, message:"Program cannot be deleted ...", errors:[]}
```

### What this class deliberately does *not* do
- **It doesn't build JSON.** That stays in `bootstrap/app.php` so the envelope has one home.
- **It doesn't cover validation-type rules** ("code must be unique", "end date after start date"). Those stay in the Form Requests and produce a 422.
- **It isn't a place for different response shapes.** A rule that needs extra body fields (say, the ids of the blocking students) would get its own exception with a `render()` method — the one case where a class should build the JSON itself.

### Where it would show up again (Phase 5)
Same base idea, other statuses — these would be separate small classes, not more parameters on this one:
| Rule | Likely status | Symfony base |
|---|---|---|
| Duplicate enrollment in the same offering | 409 | `ConflictHttpException` |
| Course offering is full | 409 (or 422) | `ConflictHttpException` |

---

## 3. Open decisions

- [ ] **`app/Exceptions` folder** — needs approval under the project rule "don't create new base folders without approval". Nothing has been created.
- [ ] **When to introduce the class** — now (a single `abort(409, ...)` exists today), or after the second resource so the repetition is visible first.
- [ ] **One class or four** — a generic `RecordInUseException` (sketched above) vs. one class per rule (`ProgramHasStudentsException`, …). The generic version is less code; the per-rule version gives each rule a name that can be asserted in tests.
- [ ] **Framework-side gaps in `bootstrap/app.php`** (independent of this sketch): a `NotFoundHttpException` callback with a fixed message (the current 404 leaks `App\Models\Program`), and a `Throwable` catch-all last, returning a fixed "Server error.".
