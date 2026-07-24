<?php

namespace App\Http\Controllers;

use App\Actions\StoreSubmissionAction;
use App\Http\Requests\StoreSubmissionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SubmissionController extends Controller
{
    public function create(): View
    {
        return view('submissions.create');
    }

    public function store(StoreSubmissionRequest $request, StoreSubmissionAction $store): RedirectResponse
    {
        $store->handle($request->validated());

        return redirect()->route('submissions.thanks');
    }

    public function thanks(): View
    {
        return view('submissions.thanks');
    }
}
