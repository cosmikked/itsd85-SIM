<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\User;

class GradePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === 'administrator') {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === 'registrar';
    }

    /**
     * Drafts are private to the offering's instructor and administrators;
     * students and registrars only see grades with a published period.
     */
    public function view(User $user, Grade $grade): bool
    {
        if ($user->role === 'registrar') {
            return $grade->isAnyPublished();
        }

        if ($user->role === 'student') {
            return $grade->isAnyPublished() && $grade->enrollment->student->user_id === $user->id;
        }

        return $this->isInstructorOf($user, $grade);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, ?Enrollment $enrollment = null): bool
    {
        if (! $enrollment) {
            return false;
        }

        return $user->role === 'instructor' && $user->id === $enrollment->courseOffering->instructor_id;
    }

    /**
     * The instructor edits drafts; GradePublicationService locks published parts.
     */
    public function update(User $user, Grade $grade): bool
    {
        return $this->isInstructorOf($user, $grade);
    }

    /**
     * Determine whether the user can publish the grade.
     */
    public function publish(User $user, Grade $grade): bool
    {
        return $this->isInstructorOf($user, $grade);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Grade $grade): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Grade $grade): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Grade $grade): bool
    {
        return false;
    }

    private function isInstructorOf(User $user, Grade $grade): bool
    {
        return $user->role === 'instructor'
            && $user->id === $grade->enrollment->courseOffering->instructor_id;
    }
}
