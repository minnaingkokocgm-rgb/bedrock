<?php

namespace App\Policies;

use App\Models\Submission;
use App\Models\User;

class SubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Submission $submission): bool
    {
        return true;
    }

    public function download(User $user, Submission $submission): bool
    {
        return true;
    }

    public function approve(User $user, Submission $submission): bool
    {
        return $submission->isPending();
    }

    public function reject(User $user, Submission $submission): bool
    {
        return $submission->isPending();
    }
}
