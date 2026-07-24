@extends('layouts.portal')

@section('title', 'Review submissions')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">Review queue</h1>
            <p class="mt-2 text-stone-600">Approve or reject pending submissions.</p>
        </div>
        <form method="GET" action="{{ route('review.submissions.index') }}" class="flex items-center gap-2">
            <label for="status" class="text-sm text-stone-600">Status</label>
            <select id="status" name="status" onchange="this.form.submit()"
                class="rounded-md border border-stone-300 bg-white px-3 py-2 text-sm shadow-sm">
                <option value="">All</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($currentStatus === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="mt-8 overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50 text-left text-stone-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Title</th>
                    <th class="px-4 py-3 font-medium">Type</th>
                    <th class="px-4 py-3 font-medium">Submitter</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">Submitted</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($submissions as $submission)
                    <tr class="hover:bg-stone-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('review.submissions.show', $submission) }}" class="font-medium text-stone-900 hover:underline">
                                {{ $submission->title }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-stone-600">{{ $submission->type->label() }}</td>
                        <td class="px-4 py-3 text-stone-600">{{ $submission->submitter_name }}</td>
                        <td class="px-4 py-3">
                            <span @class([
                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-amber-100 text-amber-800' => $submission->status->value === 'pending',
                                'bg-emerald-100 text-emerald-800' => $submission->status->value === 'approved',
                                'bg-red-100 text-red-800' => $submission->status->value === 'rejected',
                            ])>
                                {{ $submission->status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-stone-500">{{ $submission->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-stone-500">No submissions found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $submissions->links() }}
    </div>
@endsection
