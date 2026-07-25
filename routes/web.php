<?php

use App\Http\Controllers\Admin\AiSettingsController;
use App\Http\Controllers\Admin\BedrockPingController;
use App\Http\Controllers\Admin\WipeSubmissionsController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Review\SubmissionController as ReviewSubmissionController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SubmissionController::class, 'create'])->name('submissions.create');
Route::post('/submissions', [SubmissionController::class, 'store'])->name('submissions.store');
Route::get('/submissions/thanks', [SubmissionController::class, 'thanks'])->name('submissions.thanks');

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [SessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->prefix('review')->name('review.')->group(function () {
    Route::get('/submissions', [ReviewSubmissionController::class, 'index'])->name('submissions.index');
    Route::get('/submissions/{submission}', [ReviewSubmissionController::class, 'show'])->name('submissions.show');
    Route::get('/submissions/{submission}/download', [ReviewSubmissionController::class, 'download'])->name('submissions.download');
    Route::get('/submissions/{submission}/preview', [ReviewSubmissionController::class, 'preview'])->name('submissions.preview');
    Route::post('/submissions/{submission}/approve', [ReviewSubmissionController::class, 'approve'])->name('submissions.approve');
    Route::post('/submissions/{submission}/reject', [ReviewSubmissionController::class, 'reject'])->name('submissions.reject');
});

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/ai', [AiSettingsController::class, 'edit'])->name('ai.edit');
    Route::put('/ai', [AiSettingsController::class, 'update'])->name('ai.update');
    Route::get('/bedrock-ping', BedrockPingController::class)->name('bedrock.ping');
    Route::get('/wipe-submissions', [WipeSubmissionsController::class, 'show'])->name('wipe.show');
    Route::delete('/wipe-submissions', [WipeSubmissionsController::class, 'destroy'])->name('wipe.destroy');
});
