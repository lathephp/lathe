<?php

use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('refuses to delete a Post for everyone and offers no Delete on its detail page', function () {
    $post = Post::query()->whereNull('deleted_at')->firstOrFail();

    $this->deleteJson("/lathe/api/resources/posts/{$post->id}")->assertForbidden();
    $this->get("/lathe/resources/posts/{$post->id}")->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('record.abilities.delete', false)->where('record.trashed', false));
});

it('opens a trashed Post with its fields, marked trashed, with Restore and Force Delete allowed', function () {
    $post = Post::onlyTrashed()->firstOrFail();

    $this->get("/lathe/resources/posts/{$post->id}")->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('record.trashed', true)
            ->where('record.abilities.restore', true)
            ->where('record.abilities.forceDelete', true)
            ->where('record.fields.0.value', $post->title));
});

it('answers 404 on the old detail URL after a force delete', function () {
    $post = Post::onlyTrashed()->firstOrFail();

    $this->deleteJson("/lathe/api/resources/posts/{$post->id}/force")->assertOk()->assertJsonPath('redirect', 'posts');
    $this->get("/lathe/resources/posts/{$post->id}")->assertNotFound();
});

it('shows a restored Post without the trashed mark', function () {
    $post = Post::onlyTrashed()->firstOrFail();

    $this->putJson("/lathe/api/resources/posts/{$post->id}/restore")->assertOk();
    $this->get("/lathe/resources/posts/{$post->id}")->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('record.trashed', false));
});
