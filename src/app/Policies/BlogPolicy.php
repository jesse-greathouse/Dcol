<?php

namespace App\Policies;

use App\Models\Blog;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BlogPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        //
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Blog $blog): bool
    {
        return $this->can('read', $user, $blog);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Blog $blog): bool
    {
        return $this->can('create', $user, $blog);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Blog $blog): bool
    {
        return $this->can('update', $user, $blog);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Blog $blog): bool
    {
        return $this->can('delete', $user, $blog);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Blog $blog): bool
    {
        return $this->can('create', $user, $blog);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Blog $blog): bool
    {
        return $this->can('delete', $user, $blog);
    }

    /**
     * The basic standard for all of these methods.
     *
     * @param string $action
     * @param User $user
     * @param Blog $blog
     * @return boolean
     */
    protected function can(string $action, User $user, Blog $blog): bool
    {
        return $user->id === $blog->user_id && $user->tokenCan($action);
    }
}
