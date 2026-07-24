@extends('layouts.portal')

@section('title', 'Bedrock ping')

@section('content')
    <div class="mx-auto max-w-2xl">
        <h1 class="text-3xl font-semibold tracking-tight">Bedrock ping</h1>
        <p class="mt-2 text-stone-600">Simple live check that Converse works on this server (no queue, no S3).</p>

        <dl class="mt-6 grid gap-3 rounded-xl border border-stone-200 bg-white p-6 text-sm shadow-sm">
            <div>
                <dt class="font-medium text-stone-500">Model</dt>
                <dd class="mt-1 font-mono text-stone-900">{{ $modelId }}</dd>
            </div>
            <div>
                <dt class="font-medium text-stone-500">Region</dt>
                <dd class="mt-1 font-mono text-stone-900">{{ $region }}</dd>
            </div>
            <div>
                <dt class="font-medium text-stone-500">Credentials</dt>
                <dd class="mt-1 text-stone-900">
                    {{ $usingExplicitKeys ? 'AWS access keys from .env' : 'Default chain (IAM role / instance profile)' }}
                </dd>
            </div>
            <div>
                <dt class="font-medium text-stone-500">Prompt</dt>
                <dd class="mt-1 text-stone-900">{{ $prompt }}</dd>
            </div>
        </dl>

        @if ($ok)
            <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-6">
                <p class="text-sm font-medium text-emerald-800">Bedrock OK</p>
                <pre class="mt-3 whitespace-pre-wrap break-words font-mono text-sm text-emerald-950">{{ $response }}</pre>
            </div>
        @else
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-6">
                <p class="text-sm font-medium text-red-800">Bedrock failed</p>
                <pre class="mt-3 whitespace-pre-wrap break-words font-mono text-sm text-red-950">{{ $error }}</pre>
            </div>
        @endif

        <p class="mt-6 text-sm text-stone-500">
            <a href="{{ route('admin.ai.edit') }}" class="text-stone-700 underline hover:text-stone-900">Back to AI settings</a>
        </p>
    </div>
@endsection
