<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\CourseOffering;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Draft/publish lifecycle of grades.
 *
 * Midterm and final results are published independently. While a period is a
 * draft the instructor edits it freely; once published it is locked for the
 * instructor (administrators can still change it). Two narrow exceptions keep
 * the existing business rules working: a one-time re-exam score on a published
 * Conditional grade, and completing a published Incomplete (INC) grade.
 */
class GradePublicationService
{
    public const PERIODS = ['midterm', 'final'];

    private const ACTIVE_ENROLLMENT_STATUSES = ['enrolled', 'active'];

    /**
     * Abort with 403 if the instructor is trying to change a locked part of a grade.
     *
     * @param  array<string, mixed>  $data
     */
    public function assertEditable(Grade $grade, array $data, User $actor): void
    {
        if ($actor->role === 'administrator' || ! $grade->exists) {
            return;
        }

        $incCompletion = $grade->is_inc && $grade->isFinalPublished();

        if ($incCompletion) {
            return;
        }

        if ($this->scoreChanged($grade, 'midterm_raw_score', $data)
            && ($grade->isMidtermPublished() || $grade->isFinalPublished())) {
            abort(403, 'Midterm grades are already published and can no longer be changed.');
        }

        $finalChanged = $this->scoreChanged($grade, 'finalterm_raw_score', $data)
            || (array_key_exists('is_inc', $data) && (bool) $data['is_inc'] !== (bool) $grade->is_inc);

        if ($finalChanged && $grade->isFinalPublished()) {
            abort(403, 'Final grades are already published and can no longer be changed.');
        }

        if ($this->scoreChanged($grade, 're_exam_raw_score', $data)
            && $grade->isFinalPublished()
            && $grade->re_exam_raw_score !== null) {
            abort(403, 'The re-exam result is already recorded and can no longer be changed.');
        }
    }

    /**
     * Instructors must submit scores before the term's grading deadlines. Administrators are exempt.
     * A published INC grade is completed against the INC completion deadline instead.
     *
     * @param  array<string, mixed>  $data
     */
    public function assertWithinDeadlines(AcademicTerm $term, array $data, User $actor, ?Grade $grade = null): void
    {
        if ($actor->role === 'administrator') {
            return;
        }

        if ($grade && $grade->exists && $grade->is_inc && $grade->isFinalPublished()) {
            if ($term->inc_completion_deadline && today()->gt($term->inc_completion_deadline)) {
                abort(403, 'INC completion deadline has passed.');
            }

            return;
        }

        if (array_key_exists('midterm_raw_score', $data) && $this->hasPassed($term->midterm_grading_deadline)) {
            abort(403, 'Midterm grading deadline has passed.');
        }

        if (array_key_exists('finalterm_raw_score', $data) && $this->hasPassed($term->final_grading_deadline)) {
            abort(403, 'Final grading deadline has passed.');
        }
    }

    /**
     * Whether the period of this grade can be published right now.
     */
    public function qualifies(Grade $grade, string $period): bool
    {
        $grade->loadMissing('enrollment');

        if (! in_array($grade->enrollment->status, self::ACTIVE_ENROLLMENT_STATUSES, true)) {
            return false;
        }

        if ($period === 'midterm') {
            return ! $grade->isMidtermPublished() && $grade->midterm_raw_score !== null;
        }

        return ! $grade->isFinalPublished()
            && $grade->midterm_raw_score !== null
            && ($grade->finalterm_raw_score !== null || $grade->is_inc);
    }

    /**
     * Publish one period of one grade. Already-published periods are left as they are.
     */
    public function publish(Grade $grade, string $period, User $actor): Grade
    {
        $grade->loadMissing('enrollment.courseOffering.academicTerm');

        if ($this->isPublished($grade, $period)) {
            return $grade;
        }

        $this->assertPublishWindowOpen($grade->enrollment->courseOffering->academicTerm, $period, $actor);

        if (! $this->qualifies($grade, $period)) {
            abort(422, "This grade has no {$period} result that can be published yet.");
        }

        $this->markPublished($grade, $period);

        return $grade;
    }

    /**
     * Publish every qualifying grade of an offering for one period.
     * Anything that does not qualify is skipped and can be published later.
     *
     * @return array{period: string, published: int, skipped: int}
     */
    public function publishOffering(CourseOffering $offering, string $period, User $actor): array
    {
        $offering->loadMissing('academicTerm');
        $this->assertPublishWindowOpen($offering->academicTerm, $period, $actor);

        return DB::transaction(function () use ($offering, $period) {
            $enrollments = $offering->enrollments()
                ->whereIn('status', self::ACTIVE_ENROLLMENT_STATUSES)
                ->with('grade')
                ->get();

            $published = 0;

            foreach ($enrollments as $enrollment) {
                $grade = $enrollment->grade;

                if ($grade && $this->qualifies($grade, $period)) {
                    $this->markPublished($grade, $period);
                    $published++;
                }
            }

            return [
                'period' => $period,
                'published' => $published,
                'skipped' => $enrollments->count() - $published,
            ];
        });
    }

    private function markPublished(Grade $grade, string $period): void
    {
        $grade->forceFill([
            "{$period}_status" => 'published',
            "{$period}_published_at" => now(),
        ])->save();
    }

    private function isPublished(Grade $grade, string $period): bool
    {
        return $period === 'midterm' ? $grade->isMidtermPublished() : $grade->isFinalPublished();
    }

    private function assertPublishWindowOpen(AcademicTerm $term, string $period, User $actor): void
    {
        if ($actor->role === 'administrator') {
            return;
        }

        $deadline = $period === 'midterm' ? $term->midterm_grading_deadline : $term->final_grading_deadline;

        if ($this->hasPassed($deadline)) {
            abort(403, ucfirst($period).' grading deadline has passed.');
        }
    }

    private function hasPassed(mixed $deadline): bool
    {
        return $deadline !== null && now()->gt($deadline);
    }

    /**
     * A score counts as changed only when the submitted value differs from the stored one,
     * so clients may resend unchanged values for a locked period.
     *
     * @param  array<string, mixed>  $data
     */
    private function scoreChanged(Grade $grade, string $field, array $data): bool
    {
        if (! array_key_exists($field, $data)) {
            return false;
        }

        $new = $data[$field];
        $old = $grade->{$field};

        if ($new === null || $old === null) {
            return $new !== $old;
        }

        return abs((float) $new - (float) $old) > 0.001;
    }
}
