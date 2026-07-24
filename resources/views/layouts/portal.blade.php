<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Submission Portal') — {{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-100 text-stone-900 antialiased">
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <a href="{{ route('submissions.create') }}" class="text-lg font-semibold tracking-tight text-stone-900">
                Submission Portal
            </a>
            <nav class="flex items-center gap-3 text-sm">
                <a href="{{ route('submissions.create') }}" class="text-stone-600 hover:text-stone-900">Submit</a>
                @auth
                    <a href="{{ route('review.submissions.index') }}" class="text-stone-600 hover:text-stone-900">Review</a>
                    <a href="{{ route('admin.ai.edit') }}" class="text-stone-600 hover:text-stone-900">AI settings</a>
                    <a href="{{ route('admin.bedrock.ping') }}" class="text-stone-600 hover:text-stone-900">Bedrock ping</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-stone-600 hover:text-stone-900">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="rounded-md bg-stone-900 px-3 py-1.5 text-white hover:bg-stone-700">Log in</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        @if (session('status'))
            <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
