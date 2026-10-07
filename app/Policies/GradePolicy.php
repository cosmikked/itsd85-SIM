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

        if ($user->role === 'registrar') {
            if ($this instanceof UserPolicy || $this instanceof GradePolicy) {
                return null;
            }

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
     * Determine whether the user can view the model.
     */
    public function view(User $user, Grade $grade): bool
    {
        if ($user->role === 'registrar') {
            return true;
        }

        if ($user->role === 'student') {
            return $grade->enrollment->student->user_id === $user->id;
        }

        if ($user->role === 'instructor') {
            return $user->id === $grade->enrollment->courseOffering->instructor_id;
        }

        return false;
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
     * Determine whether the user can update the model.
     */
    public function update(User $user, Grade $grade): bool
    {
        return false;
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
}
