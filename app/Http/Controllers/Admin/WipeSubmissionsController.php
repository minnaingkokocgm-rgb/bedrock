<?php

namespace App\Http\Controllers\Admin;

use App\Actions\WipeSubmissionsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\WipeSubmissionsRequest;
use App\Models\Submission;
use App\Models\SubmissionAiAdvice;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WipeSubmissionsController extends Controller
{
    public function show(): View
    {
        return view('admin.wipe-submissions', [
            'submissionCount' => Submission::query()->count(),
            'adviceCount' => SubmissionAiAdvice::query()->count(),
            's3Count' => Submission::query()->where('disk', 's3')->count(),
            'localCount' => Submission::query()->where('disk', 'local')->count(),
        ]);
    }

    public function destroy(WipeSubmissionsRequest $request, WipeSubmissionsAction $action): RedirectResponse
    {
        $stats = $action->handle();

        return redirect()
            ->route('admin.wipe.show')
            ->with(
                'status',
                sprintf(
                    'Wiped %d submission(s): %d file(s) deleted, %d missing, %d failed.',
                    $stats['deleted_records'],
                    $stats['deleted_files'],
                    $stats['missing_files'],
                    $stats['failed_files'],
                ),
            );
    }
}
