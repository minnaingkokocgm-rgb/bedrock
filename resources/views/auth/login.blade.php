@extends('layouts.portal')

@section('title', 'Log in')

@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="text-3xl font-semibold tracking-tight">Reviewer login</h1>
        <p class="mt-2 text-stone-600">Sign in to approve or reject submissions.</p>

        <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5 rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-stone-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                    class="mt-1 w-full rounded-md border border-stone-300 px-3 py-2 shadow-sm focus:border-stone-500 focus:outline-none focus:ring-1 focus:ring-stone-500">
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-stone-700">Password</label>
                <input id="password" name="password" type="password" required
                    class="mt-1 w-full rounded-md border border-stone-300 px-3 py-2 shadow-sm focus:border-stone-500 focus:outline-none focus:ring-1 focus:ring-stone-500">
                @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="w-full rounded-md bg-stone-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-stone-700">
                Log in
            </button>
        </form>
    </div>
@endsection
