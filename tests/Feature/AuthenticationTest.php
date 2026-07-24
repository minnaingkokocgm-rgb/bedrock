<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('shows the login page', function () {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('Reviewer login');
});

it('logs a reviewer in with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'reviewer@example.com',
        'password' => 'password',
    ]);

    $this->post(route('login.store'), [
        'email' => 'reviewer@example.com',
        'password' => 'password',
    ])->assertRedirect(route('review.submissions.index'));

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function () {
    User::factory()->create([
        'email' => 'reviewer@example.com',
        'password' => 'password',
    ]);

    $this->from(route('login'))
        ->post(route('login.store'), [
            'email' => 'reviewer@example.com',
            'password' => 'wrong-password',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs a reviewer out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('submissions.create'));

    $this->assertGuest();
});
