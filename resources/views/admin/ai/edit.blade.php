@extends('layouts.portal')

@section('title', 'AI settings')

@section('content')
    <div class="mx-auto max-w-3xl">
        <h1 class="text-3xl font-semibold tracking-tight">AI settings</h1>
        <p class="mt-2 text-stone-600">
            Edit the system prompt and per-type rules used for advisory recommendations. AI never auto-approves or rejects submissions.
        </p>

        <form method="POST" action="{{ route('admin.ai.update') }}" class="mt-8 space-y-6">
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
                <label for="system_prompt" class="block text-sm font-medium text-stone-700">System prompt</label>
                <textarea id="system_prompt" name="system_prompt" rows="10" required
                    class="mt-2 w-full rounded-md border border-stone-300 px-3 py-2 text-sm shadow-sm focus:border-stone-500 focus:outline-none focus:ring-1 focus:ring-stone-500">{{ old('system_prompt', $settings->system_prompt) }}</textarea>
                @error('system_prompt')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
                <label for="document_rules" class="block text-sm font-medium text-stone-700">Document rules</label>
                <textarea id="document_rules" name="document_rules" rows="8" required
                    class="mt-2 w-full rounded-md border border-stone-300 px-3 py-2 text-sm shadow-sm focus:border-stone-500 focus:outline-none focus:ring-1 focus:ring-stone-500">{{ old('document_rules', $documentRules) }}</textarea>
                @error('document_rules')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
                <label for="image_rules" class="block text-sm font-medium text-stone-700">Image rules</label>
                <textarea id="image_rules" name="image_rules" rows="8" required
                    class="mt-2 w-full rounded-md border border-stone-300 px-3 py-2 text-sm shadow-sm focus:border-stone-500 focus:outline-none focus:ring-1 focus:ring-stone-500">{{ old('image_rules', $imageRules) }}</textarea>
                @error('image_rules')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
                <label for="video_rules" class="block text-sm font-medium text-stone-700">Video rules</label>
                <textarea id="video_rules" name="video_rules" rows="8" required
                    class="mt-2 w-full rounded-md border border-stone-300 px-3 py-2 text-sm shadow-sm focus:border-stone-500 focus:outline-none focus:ring-1 focus:ring-stone-500">{{ old('video_rules', $videoRules) }}</textarea>
                @error('video_rules')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="rounded-md bg-stone-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-stone-700">
                Save AI settings
            </button>
        </form>
    </div>
@endsection
