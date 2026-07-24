<?php

namespace App\Actions;

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\User;
use RuntimeException;

class RejectSubmissionAction
{
    public function handle(Submission $submission, User $reviewer, string $reason): Submission
    {
        if (! $submission->isPending()) {
            throw new RuntimeException('Only pending submissions can be rejected.');
        }

        $submission->update([
            'status' => SubmissionStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        return $submission->refresh();
    }
}
