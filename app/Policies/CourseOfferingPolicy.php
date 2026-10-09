<?php

namespace App\Policies;

use App\Models\CourseOffering;
use App\Models\User;

class CourseOfferingPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === 'administrator') {
            return true;
        }

        // registrars manage offerings but do not encode grades
        if ($user->role === 'registrar' && $ability !== 'encodeGrades') {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can see the students enrolled in the offering.
     */
    public function viewRoster(User $user, CourseOffering $courseOffering): bool
    {
        return $user->role === 'instructor' && $user->id === $courseOffering->instructor_id;
    }

    /**
     * Determine whether the user can encode grades for the whole offering.
     */
    public function encodeGrades(User $user, CourseOffering $courseOffering): bool
    {
        return $user->role === 'instructor' && $user->id === $courseOffering->instructor_id;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === 'instructor';
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CourseOffering $courseOffering): bool
    {
        if ($user->role === 'instructor') {
            return $user->id === $courseOffering->instructor_id;
        }

        if ($user->role === 'student') {
            return $courseOffering->enrollments()
                ->whereHas('student', fn ($query) => $query->where('user_id', $user->id))
                ->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CourseOffering $courseOffering): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CourseOffering $courseOffering): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CourseOffering $courseOffering): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CourseOffering $courseOffering): bool
    {
        return false;
    }
}
