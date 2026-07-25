@extends('layouts.portal')

@section('title', 'Submit a file')

@section('content')
    <div class="mx-auto max-w-2xl">
        <h1 class="text-3xl font-semibold tracking-tight">Submit a file</h1>
        <p class="mt-2 text-stone-600">Upload a document, image, or video for review.</p>

        <form method="POST" action="{{ route('submissions.store') }}" enctype="multipart/form-data" class="mt-8 space-y-5 rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            @csrf

            <div>
                <label for="title" class="block text-sm font-medium text-stone-700">Title</label>
                <input id="title" name="title" type="text" value="{{ old('title') }}" required
                    class="mt-1 w-full rounded-md border border-stone-300 px-3 py-2 shadow-sm focus:border-stone-500 focus:outline-none focus:ring-1 focus:ring-stone-500">
                @error('title')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-stone-700">Description <span class="font-normal text-stone-400">(optional)</span></label>
                <textarea id="description" name="description" rows="4"
                    class="mt-1 w-full rounded-md border border-stone-300 px-3 py-2 shadow-sm focus:border-stone-500 focus:outline-none focus:ring-1 focus:ring-stone-500">{{ old('description') }}</textarea>
                @error('description')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="submitter_name" class="block text-sm font-medium text-stone-700">Your name</label>
                    <input id="submitter_name" name="submitter_name" type="text" value="{{ old('submitter_name') }}" required
                        class="mt-1 w-full rounded-md border border-stone-300 px-3 py-2 shadow-sm focus:border-stone-500 focus:outline-none focus:ring-1 focus:ring-stone-500">
                    @error('submitter_name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="submitter_email" class="block text-sm font-medium text-stone-700">Your email</label>
                    <input id="submitter_email" name="submitter_email" type="email" value="{{ old('submitter_email') }}" required
                        class="mt-1 w-full rounded-md border border-stone-300 px-3 py-2 shadow-sm focus:border-stone-500 focus:outline-none focus:ring-1 focus:ring-stone-500">
                    @error('submitter_email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="file" class="block text-sm font-medium text-stone-700">File</label>
                <input id="file" name="file" type="file" required
                    accept=".jpg,.jpeg,.png,.gif,.ai,.psd,.webp,.svg,.apng,.avif,.bmp,.ico,.pdf,.doc,.docx,.xls,.xlsx,.ods,.odt,.pps,.ppt,.pptx,.wpd,.txt,.rtf,.csv,.mp4,.mov,.avi,.mkv,.webm,.wmv,.m4v"
                    class="mt-1 block w-full text-sm text-stone-600 file:mr-4 file:rounded-md file:border-0 file:bg-stone-900 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-stone-700">
                <p class="mt-2 text-xs text-stone-500">
                    Images (jpg, png, gif, ai, psd, webp, svg, …) up to 10MB ·
                    Documents (pdf, docx, xlsx, pptx, csv, …) up to 20MB ·
                    Videos (mp4, mov, avi, mkv, …) up to 1024MB.
                </p>
                @error('file')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="w-full rounded-md bg-stone-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-stone-700 sm:w-auto">
                Submit for review
            </button>
        </form>
    </div>
@endsection
