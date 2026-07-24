@php
    use App\Enums\SubmissionType;
@endphp

@extends('layouts.portal')

@section('title', $submission->title)

@section('content')
    <div class="mb-6">
        <a href="{{ route('review.submissions.index') }}" class="text-sm text-stone-600 hover:text-stone-900">&larr; Back to queue</a>
    </div>

    <div class="grid gap-8 lg:grid-cols-[1.4fr_1fr]">
        <div class="space-y-6">
            <div class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">{{ $submission->title }}</h1>
                        <p class="mt-1 text-sm text-stone-500">
                            {{ $submission->type->label() }} · {{ number_format($submission->size / 1024, 1) }} KB · {{ $submission->original_filename }}
                        </p>
                    </div>
                    <span @class([
                        'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
                        'bg-amber-100 text-amber-800' => $submission->status->value === 'pending',
                        'bg-emerald-100 text-emerald-800' => $submission->status->value === 'approved',
                        'bg-red-100 text-red-800' => $submission->status->value === 'rejected',
                    ])>
                        {{ $submission->status->label() }}
                    </span>
                </div>

                @if ($submission->description)
                    <p class="mt-4 whitespace-pre-wrap text-stone-700">{{ $submission->description }}</p>
                @endif

                <dl class="mt-6 grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-stone-500">Submitter</dt>
                        <dd class="font-medium">{{ $submission->submitter_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Email</dt>
                        <dd class="font-medium">{{ $submission->submitter_email }}</dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Submitted</dt>
                        <dd class="font-medium">{{ $submission->created_at->toDayDateTimeString() }}</dd>
                    </div>
                    @if ($submission->reviewed_at)
                        <div>
                            <dt class="text-stone-500">Reviewed</dt>
                            <dd class="font-medium">
                                {{ $submission->reviewed_at->toDayDateTimeString() }}
                                @if ($submission->reviewer)
                                    by {{ $submission->reviewer->name }}
                                @endif
                            </dd>
                        </div>
                    @endif
                </dl>

                @if ($submission->rejection_reason)
                    <div class="mt-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <p class="font-medium">Rejection reason</p>
                        <p class="mt-1">{{ $submission->rejection_reason }}</p>
                    </div>
                @endif
            </div>

            <div class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold">Preview</h2>
                <div class="mt-4">
                    @if ($submission->type === SubmissionType::Image)
                        <img
                            src="{{ route('review.submissions.preview', $submission) }}"
                            alt="{{ $submission->title }}"
                            class="max-h-[28rem] w-full rounded-md object-contain bg-stone-50"
                        >
                    @elseif ($submission->type === SubmissionType::Video)
                        <video
                            controls
                            class="w-full rounded-md bg-black"
                            src="{{ route('review.submissions.preview', $submission) }}"
                        ></video>
                    @else
                        <p class="text-sm text-stone-600">Document preview is not available inline. Download the file to review it.</p>
                    @endif
                </div>
                <a href="{{ route('review.submissions.download', $submission) }}"
                    class="mt-4 inline-block text-sm font-medium text-stone-900 underline underline-offset-2">
                    Download {{ $submission->original_filename }}
                </a>
            </div>
        </div>

        <aside class="space-y-6">
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-amber-950">AI recommendation</h2>
                <p class="mt-1 text-xs text-amber-800">Advisory only — humans make the final decision.</p>

                @if (! $advice)
                    <p class="mt-4 text-sm text-amber-900">No AI advice has been recorded yet.</p>
                @elseif ($advice->status->value === 'pending')
                    <p class="mt-4 text-sm text-amber-900">AI analysis is still pending.</p>
                @elseif ($advice->status->value === 'failed')
                    <p class="mt-4 text-sm text-amber-900">AI analysis failed.</p>
                    @if ($advice->ai_reason)
                        <p class="mt-2 text-sm text-amber-900">{{ $advice->ai_reason }}</p>
                    @endif
                @else
                    <dl class="mt-4 space-y-3 text-sm">
                        <div>
                            <dt class="text-amber-800">Verdict</dt>
                            <dd class="font-semibold text-amber-950">{{ $advice->ai_verdict?->label() ?? 'Unknown' }}</dd>
                        </div>
                        <div>
                            <dt class="text-amber-800">Reason</dt>
                            <dd class="text-amber-950 whitespace-pre-wrap">{{ $advice->ai_reason }}</dd>
                        </div>
                        <div>
                            <dt class="text-amber-800">Extraction</dt>
                            <dd class="text-amber-950">{{ $advice->extraction_status->label() }}</dd>
                        </div>
                        @if ($advice->extraction_status->value === 's3_referenced' && $advice->extracted_content)
                            <div>
                                <dt class="text-amber-800">S3 URI</dt>
                                <dd class="break-all font-mono text-xs text-amber-950">{{ $advice->extracted_content }}</dd>
                            </div>
                        @endif
                    </dl>
                @endif
            </div>

            @if ($submission->isPending())
                <div class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold">Decision</h2>

                    <form method="POST" action="{{ route('review.submissions.approve', $submission) }}" class="mt-4">
                        @csrf
                        <button type="submit" class="w-full rounded-md bg-emerald-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-600">
                            Approve
                        </button>
                    </form>

                    <form method="POST" action="{{ route('review.submissions.reject', $submission) }}" class="mt-6 space-y-3 border-t border-stone-100 pt-6">
                        @csrf
                        <label for="rejection_reason" class="block text-sm font-medium text-stone-700">Rejection reason</label>
                        <textarea id="rejection_reason" name="rejection_reason" rows="4" required
                            class="w-full rounded-md border border-stone-300 px-3 py-2 text-sm shadow-sm focus:border-stone-500 focus:outline-none focus:ring-1 focus:ring-stone-500">{{ old('rejection_reason') }}</textarea>
                        @error('rejection_reason')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <button type="submit" class="w-full rounded-md bg-red-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-red-600">
                            Reject
                        </button>
                    </form>
                </div>
            @else
                <div class="rounded-xl border border-stone-200 bg-stone-50 p-6 text-sm text-stone-600">
                    This submission has already been {{ $submission->status->value }}.
                </div>
            @endif
        </aside>
    </div>
@endsection
