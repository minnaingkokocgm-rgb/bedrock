@extends('layouts.portal')

@section('title', 'Submit a file')

@section('content')
    <div class="mx-auto max-w-2xl">
        <h1 class="text-3xl font-semibold tracking-tight">Submit a file</h1>
        <p class="mt-2 text-stone-600">Upload a document, image, or video — or point Bedrock at an existing S3 object.</p>

        <form method="POST" action="{{ route('submissions.store') }}" enctype="multipart/form-data" class="mt-8 space-y-5 rounded-xl border border-stone-200 bg-white p-6 shadow-sm" id="submission-form">
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

            <fieldset>
                <legend class="block text-sm font-medium text-stone-700">Source</legend>
                <div class="mt-2 flex flex-wrap gap-4">
                    <label class="inline-flex items-center gap-2 text-sm text-stone-700">
                        <input type="radio" name="source" value="upload" class="source-toggle border-stone-300 text-stone-900 focus:ring-stone-500"
                            @checked(old('source', 'upload') === 'upload')>
                        Upload file
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-stone-700">
                        <input type="radio" name="source" value="s3_uri" class="source-toggle border-stone-300 text-stone-900 focus:ring-stone-500"
                            @checked(old('source') === 's3_uri')>
                        S3 URI
                    </label>
                </div>
                @error('source')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </fieldset>

            <div id="upload-fields" @class(['hidden' => old('source') === 's3_uri'])>
                <label for="file" class="block text-sm font-medium text-stone-700">File</label>
                <input id="file" name="file" type="file"
                    accept=".jpg,.jpeg,.png,.gif,.ai,.psd,.webp,.svg,.apng,.avif,.bmp,.ico,.pdf,.doc,.docx,.xls,.xlsx,.ods,.odt,.pps,.ppt,.pptx,.wpd,.txt,.rtf,.csv,.mp4,.mov,.avi,.mkv,.webm,.wmv,.m4v"
                    class="mt-1 block w-full text-sm text-stone-600 file:mr-4 file:rounded-md file:border-0 file:bg-stone-900 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-stone-700"
                    @if(old('source', 'upload') === 'upload') required @endif>
                <p class="mt-2 text-xs text-stone-500">
                    Images (jpg, png, gif, ai, psd, webp, svg, …) up to 10MB ·
                    Documents (pdf, docx, xlsx, pptx, csv, …) up to 20MB ·
                    Videos (mp4, mov, avi, mkv, …) up to 1024MB.
                </p>
                @error('file')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div id="s3-uri-fields" @class(['hidden' => old('source', 'upload') !== 's3_uri'])>
                <label for="s3_uri" class="block text-sm font-medium text-stone-700">S3 URI</label>
                <input id="s3_uri" name="s3_uri" type="text" value="{{ old('s3_uri') }}"
                    placeholder="s3://{{ config('filesystems.disks.s3.bucket') ?: 'your-bucket' }}/path/to/file.pdf"
                    class="mt-1 w-full rounded-md border border-stone-300 px-3 py-2 font-mono text-sm shadow-sm focus:border-stone-500 focus:outline-none focus:ring-1 focus:ring-stone-500"
                    @if(old('source') === 's3_uri') required @endif>
                <p class="mt-2 text-xs text-stone-500">
                    Must use the configured bucket
                    @if (filled(config('filesystems.disks.s3.bucket')))
                        (<code class="rounded bg-stone-100 px-1">{{ config('filesystems.disks.s3.bucket') }}</code>).
                    @else
                        (<code class="rounded bg-stone-100 px-1">AWS_BUCKET</code>).
                    @endif
                    Skips upload — Bedrock reads the object directly.
                </p>
                @error('s3_uri')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="w-full rounded-md bg-stone-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-stone-700 sm:w-auto">
                Submit for review
            </button>
        </form>
    </div>

    <script>
        (() => {
            const form = document.getElementById('submission-form');
            if (!form) return;

            const uploadFields = document.getElementById('upload-fields');
            const s3Fields = document.getElementById('s3-uri-fields');
            const fileInput = document.getElementById('file');
            const s3Input = document.getElementById('s3_uri');

            const sync = () => {
                const source = form.querySelector('input[name="source"]:checked')?.value ?? 'upload';
                const isUpload = source === 'upload';

                uploadFields.classList.toggle('hidden', !isUpload);
                s3Fields.classList.toggle('hidden', isUpload);
                fileInput.required = isUpload;
                s3Input.required = !isUpload;
            };

            form.querySelectorAll('.source-toggle').forEach((input) => {
                input.addEventListener('change', sync);
            });

            sync();
        })();
    </script>
@endsection
