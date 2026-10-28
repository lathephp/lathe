<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lathe\Preferences\UserPreference;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => in_array($user->email, ['admin@lathephp.com', 'editor@lathephp.com'], true));
});

function previewAs(string $email): User
{
    $user = User::where('email', $email)->firstOrFail();
    test()->actingAs($user);

    return $user;
}

const PREVIEW_URL = '/lathe/api/resources/orders/preview-panel';

it('opens the panel for the user who saved it, per Resource', function () {
    previewAs('admin@lathephp.com');

    $this->putJson(PREVIEW_URL, ['open' => true])->assertNoContent();

    expect($this->getJson('/lathe/api/resources/orders')->assertOk()->json('table.previewOpen'))->toBeTrue()
        ->and($this->getJson('/lathe/api/resources/customers')->assertOk()->json('table.previewOpen'))->toBeFalse();

    previewAs('editor@lathephp.com');

    expect($this->getJson('/lathe/api/resources/orders')->assertOk()->json('table.previewOpen'))->toBeFalse();
});

it('closes the panel again', function () {
    previewAs('admin@lathephp.com');
    $this->putJson(PREVIEW_URL, ['open' => true])->assertNoContent();

    $this->putJson(PREVIEW_URL, ['open' => false])->assertNoContent();

    expect($this->getJson('/lathe/api/resources/orders')->json('table.previewOpen'))->toBeFalse()
        ->and(UserPreference::where('key', 'preview:orders')->sole()->value)->toBe(['open' => false]);
});

it('refuses a non-boolean and a user without the Resource', function () {
    previewAs('admin@lathephp.com');
    $this->putJson(PREVIEW_URL, ['open' => 'yes'])->assertUnprocessable();
    $this->putJson(PREVIEW_URL, [])->assertUnprocessable();

    expect(UserPreference::count())->toBe(0);

    previewAs('guest@lathephp.com');
    $this->putJson(PREVIEW_URL, ['open' => true])->assertForbidden();
});

it('reads the columns row and the preview row in one preferences query', function () {
    previewAs('admin@lathephp.com');
    $this->putJson('/lathe/api/resources/orders/columns', ['order' => [], 'hidden' => ['customer'], 'pinned' => []])->assertOk();
    $this->putJson(PREVIEW_URL, ['open' => true])->assertNoContent();

    $sql = [];
    DB::listen(function ($event) use (&$sql) {
        $sql[] = $event->sql;
    });
    $table = $this->getJson('/lathe/api/resources/orders')->assertOk()->json('table');
    $preferences = array_filter($sql, fn (string $query) => str_contains($query, 'lathe_user_preferences'));

    expect($preferences)->toHaveCount(1)
        ->and($table['previewOpen'])->toBeTrue()
        ->and($table['columnChoice']['hidden'])->toBe(['customer'])
        ->and(array_column($table['columns'], 'key'))->toBe(['number', 'status', 'total_cents', 'placed_at']);
});
