<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('redirects guests from the submission form to login', function () {
    $this->get(route('submissions.create'))
        ->assertRedirect(route('login'));
});

it('redirects guests from submission store to login', function () {
    Queue::fake();

    $this->post(route('submissions.store'), [
        'title' => 'Secret',
        'submitter_name' => 'Ada',
        'submitter_email' => 'ada@example.com',
        'source' => 'upload',
        'file' => UploadedFile::fake()->create('brief.pdf', 200, 'application/pdf'),
    ])->assertRedirect(route('login'));
});

it('allows authenticated users to view the submission form', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('submissions.create'))
        ->assertSuccessful()
        ->assertSee('Submit a file')
        ->assertSee('S3 URI');
});
