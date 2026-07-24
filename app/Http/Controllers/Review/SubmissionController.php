<?php

namespace App\Http\Controllers\Review;

use App\Actions\ApproveSubmissionAction;
use App\Actions\RejectSubmissionAction;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectSubmissionRequest;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Submission::class);

        $status = $request->string('status')->toString();

        $submissions = Submission::query()
            ->when(
                $status !== '' && SubmissionStatus::tryFrom($status),
                fn ($query) => $query->where('status', $status),
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('review.submissions.index', [
            'submissions' => $submissions,
            'currentStatus' => $status,
            'statuses' => SubmissionStatus::cases(),
        ]);
    }

    public function show(Submission $submission): View
    {
        $this->authorize('view', $submission);

        $submission->load(['reviewer', 'aiAdvice']);

        return view('review.submissions.show', [
            'submission' => $submission,
            'advice' => $submission->aiAdvice,
        ]);
    }

    public function approve(Submission $submission, ApproveSubmissionAction $approve): RedirectResponse
    {
        $this->authorize('approve', $submission);

        $approve->handle($submission, request()->user());

        return redirect()
            ->route('review.submissions.show', $submission)
            ->with('status', 'Submission approved.');
    }

    public function reject(
        RejectSubmissionRequest $request,
        Submission $submission,
        RejectSubmissionAction $reject,
    ): RedirectResponse {
        $reject->handle(
            $submission,
            $request->user(),
            $request->validated('rejection_reason'),
        );

        return redirect()
            ->route('review.submissions.show', $submission)
            ->with('status', 'Submission rejected.');
    }

    public function download(Submission $submission): StreamedResponse
    {
        $this->authorize('download', $submission);

        abort_unless($submission->fileExists(), 404);

        return Storage::disk($submission->disk)->download(
            $submission->disk_path,
            $submission->original_filename,
        );
    }

    public function preview(Submission $submission): StreamedResponse
    {
        $this->authorize('view', $submission);

        abort_unless($submission->fileExists(), 404);

        return Storage::disk($submission->disk)->response(
            $submission->disk_path,
            $submission->original_filename,
            [
                'Content-Type' => $submission->mime_type,
            ],
        );
    }
}
