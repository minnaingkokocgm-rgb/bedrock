<?php

namespace App\Actions;

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\User;
use RuntimeException;

class ApproveSubmissionAction
{
    public function handle(Submission $submission, User $reviewer): Submission
    {
        if (! $submission->isPending()) {
            throw new RuntimeException('Only pending submissions can be approved.');
        }

        $submission->update([
            'status' => SubmissionStatus::Approved,
            'rejection_reason' => null,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        return $submission->refresh();
    }
}
