# API Query Parameters Documentation

This API supports querying features like **pagination**, **searching**, **filtering**, and **sorting** on index (`GET`) endpoints. This document outlines which parameters are supported by each endpoint.

## Common Parameters

The following parameters are standard for paginated resource endpoints. Note that some endpoints (like nested relationship routes) might only support basic pagination and not sorting or filtering.

| Parameter  | Description | Default | Example |
|---|---|---|---|
| `page` | The page number to retrieve. | `1` | `?page=2` |
| `per_page` | Number of items per page. | `15` | `?per_page=50` |
| `sort` | Comma-separated list of columns to sort by. Prefix with `-` for descending order. | *None* | `?sort=-year_level,last_name` |

---

## Standard Resource Endpoints

### 1. Users (`/api/v1/users`)
- **Querying Support:** Pagination only (`page`, `per_page`).
- *Note: Advanced filtering/sorting is not currently implemented on this endpoint.*

### 2. Students (`/api/v1/students`)
- **Search (`?search=`)**: Searches `first_name`, `last_name`, and `student_number`.
- **Filters**:
  - `status`: Filter by exact status (e.g., `regular`, `irregular`).
  - `program_id`: Filter by the ID of the enrolled program.
  - `year_level`: Filter by the specific year level (1-4).

### 3. Programs (`/api/v1/programs`)
- **Search (`?search=`)**: Searches `name` and `code`.
- **Filters**:
  - `status`: Filter by exact status (`active`, `inactive`).

### 4. Courses (`/api/v1/courses`)
- **Search (`?search=`)**: Searches `course_title` and `course_code`.
- **Filters**:
  - `status`: Filter by exact status (`active`, `inactive`).

### 5. Academic Terms (`/api/v1/academic-terms`)
- **Search (`?search=`)**: Searches `academic_year`.
- **Filters**:
  - `status`: Filter by exact status.
  - `academic_year`: Filter by exact academic year (e.g., `2024-2025`).
  - `term`: Filter by exact term (`First Semester`, `Second Semester`, `Summer`).

### 6. Course Offerings (`/api/v1/course-offerings`)
- **Search (`?search=`)**: Searches `course.course_title` and `course.course_code` (Relational).
- **Filters**:
  - `status`: Filter by exact status.
  - `course_id`: Filter by the specific course.
  - `academic_term_id`: Filter by the specific term.
  - `instructor_id`: Filter by the specific instructor.

### 7. Enrollments (`/api/v1/enrollments`)
- **Search (`?search=`)**: Searches `student.student_number` (Relational).
- **Filters**:
  - `status`: Filter by exact status (e.g., `enrolled`, `dropped`).
  - `student_id`: Filter by the specific student.
  - `course_offering_id`: Filter by the specific course offering.

### 8. Grades (`/api/v1/grades`)
- **Search (`?search=`)**: Searches `enrollment.student.student_number` (Deep Relational).
- **Filters**:
  - `is_inc`: Filter by incomplete status (`true` or `false`).
  - `enrollment_id`: Filter by the specific enrollment record.

### 9. Rooms (`/api/v1/rooms`) — read-only lookup, admin and registrar
- **Search (`?search=`)**: Searches `code` and `building`.
- **Filters**:
  - `building`: Filter by exact building name.
- Only `GET /rooms` and `GET /rooms/{room}` exist.

### 10. Instructors (`/api/v1/instructors`) — read-only lookup, admin and registrar
- Returns instructor accounts only, with `id`, `name`, `email`, `status`. (`/users` stays administrator-only.)
- **Search (`?search=`)**: Searches `name` and `email`.
- **Filters**:
  - `status`: `active` or `inactive`.

---

## Nested Resource Endpoints

These endpoints provide contextual data based on relationships. They support standard pagination but do not support advanced search, filtering, or sorting via query parameters.

### 1. Student Relationships
- **`GET /api/v1/students/{student}/enrollments`**: Returns a paginated list of enrollments for the student. Supports `page` and `per_page`.
- **`GET /api/v1/students/{student}/grades`**: Returns a paginated list of grades for the student. Supports `page` and `per_page`.
- **`GET /api/v1/students/{student}/academic-record`**: Returns the complete, unpaginated academic record grouped by term. *Does not support query parameters.*

### 2. Course Offering Relationships
- **`GET /api/v1/course-offerings/{course_offering}/students`**: Returns a paginated list of students enrolled in the offering. Supports `page` and `per_page`.
- **`GET /api/v1/course-offerings/{course_offering}/enrollments`**: The grading-sheet roster. Every enrollment of the offering (all statuses) with a `student` summary and the `grade` (drafts are only visible to the instructor and administrators; the registrar gets `grade: null` until a period is published). Supports `search` (student number, first/last name), `status`, `sort`, `page`, `per_page`.
- **`PUT /api/v1/course-offerings/{course_offering}/grades`**: Bulk grade update, saves drafts (action endpoint, not queried).
- **`POST /api/v1/course-offerings/{course_offering}/grades/publish`**: Publishes every qualifying grade of the offering for `period` (`midterm` or `final`). Returns `data: {period, published, skipped}`.

### 3. Grade Actions
- **`POST /api/v1/grades/{grade}/publish`**: Publishes one grade for `period` (`midterm` or `final`). `422` if the period has nothing to publish yet, `403` if the grading deadline has passed (administrators are exempt).

---

## Combining Parameters Example
You can combine these parameters to build complex queries:
```
GET /api/v1/students?search=John&status=regular&year_level=3&sort=-last_name&per_page=10
```
