@extends('layouts.portal')

@section('title', 'Wipe submissions')

@section('content')
    <div class="mx-auto max-w-3xl">
        <h1 class="text-3xl font-semibold tracking-tight">Wipe submissions</h1>
        <p class="mt-2 text-stone-600">
            Permanently delete all submission records and their stored files (S3 or local). AI advice rows are removed with them.
        </p>

        <div class="mt-8 rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-medium text-stone-700">Current totals</h2>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div class="rounded-md bg-stone-50 px-3 py-2">
                    <dt class="text-stone-500">Submissions</dt>
                    <dd class="mt-0.5 text-lg font-semibold text-stone-900">{{ $submissionCount }}</dd>
                </div>
                <div class="rounded-md bg-stone-50 px-3 py-2">
                    <dt class="text-stone-500">AI advice rows</dt>
                    <dd class="mt-0.5 text-lg font-semibold text-stone-900">{{ $adviceCount }}</dd>
                </div>
                <div class="rounded-md bg-stone-50 px-3 py-2">
                    <dt class="text-stone-500">On S3</dt>
                    <dd class="mt-0.5 text-lg font-semibold text-stone-900">{{ $s3Count }}</dd>
                </div>
                <div class="rounded-md bg-stone-50 px-3 py-2">
                    <dt class="text-stone-500">On local disk</dt>
                    <dd class="mt-0.5 text-lg font-semibold text-stone-900">{{ $localCount }}</dd>
                </div>
            </dl>
        </div>

        <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-red-900">Danger zone</h2>
            <p class="mt-2 text-sm text-red-800">
                This cannot be undone. Files are deleted from each submission’s storage disk, then database rows are removed.
            </p>

            @if ($submissionCount === 0)
                <p class="mt-4 text-sm text-stone-600">There are no submissions to wipe.</p>
            @else
                <form method="POST" action="{{ route('admin.wipe.destroy') }}" class="mt-4 space-y-4"
                    onsubmit="return confirm('Really wipe ALL submissions and their files?');">
                    @csrf
                    @method('DELETE')

                    <div>
                        <label for="confirmation" class="block text-sm font-medium text-red-900">
                            Type <span class="font-mono">WIPE</span> to confirm
                        </label>
                        <input id="confirmation" name="confirmation" type="text" required autocomplete="off"
                            class="mt-2 w-full max-w-xs rounded-md border border-red-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500"
                            placeholder="WIPE">
                        @error('confirmation')
                            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit"
                        class="rounded-md bg-red-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-red-800">
                        Wipe all submissions
                    </button>
                </form>
            @endif
        </div>
    </div>
@endsection
