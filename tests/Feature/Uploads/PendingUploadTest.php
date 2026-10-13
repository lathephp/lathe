<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Lathe\Uploads\PendingUpload;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => in_array($user->email, ['admin@lathephp.com', 'editor@lathephp.com'], true));
    Storage::fake('local');
    Storage::fake('public');
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

/**
 * An upload started the given hours ago with one chunk received.
 */
function pendingUploadAged(int $hours): string
{
    test()->travel(-$hours)->hours();
    $id = (string) test()->postJson('/lathe/api/uploads', ['name' => 'a.txt', 'size' => 5, 'mime' => 'text/plain'])->assertCreated()->json('upload_id');
    test()->call('PUT', "/lathe/api/uploads/{$id}/chunks/0", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], 'hello')->assertNoContent();
    test()->travelBack();

    return $id;
}

it('keeps every pending upload column', function () {
    $id = pendingUploadAged(0);

    expect(array_keys(PendingUpload::findOrFail($id)->getAttributes()))->toEqualCanonicalizing([
        'id', 'user_id', 'field', 'disk', 'path', 'original_name', 'size', 'draft_id', 'status', 'created_at',
    ]);
});

it('prunes a pending upload 25 hours old with its file and keeps one 23 hours old', function () {
    $old = pendingUploadAged(25);
    $recent = pendingUploadAged(23);

    $this->artisan('model:prune', ['--model' => [PendingUpload::class]])->assertSuccessful();

    expect(PendingUpload::pluck('id')->all())->toBe([$recent])
        ->and(Storage::disk('local')->allFiles())->toBe(["lathe-uploads/{$recent}/0"])
        ->and(Storage::disk('local')->directories('lathe-uploads'))->toBe(["lathe-uploads/{$recent}"])
        ->and($old)->not->toBe($recent);
});
