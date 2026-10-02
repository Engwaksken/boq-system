<?php

namespace App\Policies;

use App\Models\Faq;
use App\Models\User;

class FaqPolicy
{
    /**
     * Determine whether the user can view any FAQs in the admin interface.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can view an FAQ in the admin interface.
     */
    public function view(User $user, Faq $faq): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can create FAQs.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can update an FAQ.
     */
    public function update(User $user, Faq $faq): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can delete an FAQ.
     */
    public function delete(User $user, Faq $faq): bool
    {
        return $user->isSuperAdmin();
    }
}
