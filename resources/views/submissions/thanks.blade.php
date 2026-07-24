@extends('layouts.portal')

@section('title', 'Submission received')

@section('content')
    <div class="mx-auto max-w-xl rounded-xl border border-stone-200 bg-white p-8 text-center shadow-sm">
        <h1 class="text-2xl font-semibold tracking-tight">Submission received</h1>
        <p class="mt-3 text-stone-600">Thanks — your file is pending review. You can submit another file anytime.</p>
        <a href="{{ route('submissions.create') }}" class="mt-6 inline-block rounded-md bg-stone-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-stone-700">
            Submit another file
        </a>
    </div>
@endsection
