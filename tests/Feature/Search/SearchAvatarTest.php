<?php

namespace Lathe\Tests\Feature\Search;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class AvatarPerson extends Model
{
    protected $table = 'avatar_people';

    protected $guarded = [];
}

class AvatarPeople extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(AvatarPerson::class)->title('name')->searchable(['name'])->globallySearchable()->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::text('Name'), Field::avatar('Avatar', 'avatar')];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Storage::fake('public');
    Schema::create('avatar_people', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('avatar')->nullable();
        $table->timestamps();
    });
    AvatarPerson::query()->create(['name' => 'Editor User', 'avatar' => 'avatars/editor.png']);
    AvatarPerson::query()->create(['name' => 'Admin User']);
    Gate::define('viewLathe', fn (User $user) => true);
    Gate::define('globalSearch', fn (User $user) => true);
    Lathe::registerResources(AvatarPeople::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('gives a search row the Resource avatar image, or none so the palette draws initials', function () {
    $row = fn (string $q) => collect($this->getJson("/lathe/api/search?q={$q}")->assertOk()->json('groups'))
        ->firstWhere('resourceName', 'avatar-peoples')['results'][0];

    expect($row('Editor')['avatar'])->toEndWith('avatars/editor.png')
        ->and($row('Editor')['rounded'])->toBeTrue()
        ->and($row('Admin')['title'])->toBe('Admin User')
        ->and($row('Admin')['avatar'])->toBeNull();
});
