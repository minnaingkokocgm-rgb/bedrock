<?php

namespace App\Jobs;

use App\Actions\AnalyzeSubmissionAction;
use App\Models\Submission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalyzeSubmissionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Submission $submission) {}

    public function handle(AnalyzeSubmissionAction $analyze): void
    {
        $analyze->handle($this->submission);
    }
}
