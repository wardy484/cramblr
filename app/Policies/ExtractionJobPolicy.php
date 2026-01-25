<?php

namespace App\Policies;

use App\Models\ExtractionJob;
use App\Models\User;

class ExtractionJobPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ExtractionJob $extractionJob): bool
    {
        return $user->id === $extractionJob->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ExtractionJob $extractionJob): bool
    {
        return $user->id === $extractionJob->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ExtractionJob $extractionJob): bool
    {
        return $user->id === $extractionJob->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ExtractionJob $extractionJob): bool
    {
        return $user->id === $extractionJob->user_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ExtractionJob $extractionJob): bool
    {
        return false;
    }
}
