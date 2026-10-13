<?php

namespace Lathe\Tests\Feature\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Lathe\Exceptions\InvalidConfigurationException;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class CopPost extends Resource
{
    public static bool $cop = true;

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        $definition = $definition->model(Post::class)->title('title')->canonical(false)->allowAll();

        return static::$cop ? $definition : $definition->trafficCop(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::make('Title'), Field::make('Status')];
    }
}

class StampFree extends Model
{
    protected $table = 'posts';

    public $timestamps = false;
}

class StampFreeResource extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(StampFree::class)->canonical(false)->allowAll()->trafficCop();
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    CopPost::$cop = true;
});

afterEach(function () {
    Carbon::setTestNow();
});

it('sends updatedAt as UTC ISO 8601 with milliseconds and refuses a stale save with 409', function () {
    Lathe::registerResources(CopPost::class);
    $post = Post::query()->whereNull('deleted_at')->firstOrFail();
    $token = LatheTest::resource('cop-posts')->updateFields($post->id)->assertOk()->json('updatedAt');

    expect($token)->toMatch('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3}Z$/');

    Carbon::setTestNow(now()->addSeconds(2));
    $saved = LatheTest::resource('cop-posts')->update($post->id, ['title' => 'First', '_updated_at' => $token])->assertOk();

    Carbon::setTestNow(now()->addSeconds(2));
    $stale = LatheTest::resource('cop-posts')->update($post->id, ['title' => 'Second', '_updated_at' => $token])->assertStatus(409);

    expect($stale->json('error'))->toBe('conflict')
        ->and($stale->json('message'))->toBeString()
        ->and($stale->json('updatedAt'))->toBe($saved->json('updatedAt'))
        ->and($post->fresh()?->title)->toBe('First');

    // Override: resubmit with the token the conflict returned.
    LatheTest::resource('cop-posts')->update($post->id, ['title' => 'Second', '_updated_at' => $stale->json('updatedAt')])->assertOk();
    expect($post->fresh()?->title)->toBe('Second');
});

it('never answers 409 with trafficCop(false)', function () {
    CopPost::$cop = false;
    Lathe::registerResources(CopPost::class);
    $post = Post::query()->whereNull('deleted_at')->firstOrFail();

    LatheTest::resource('cop-posts')->update($post->id, ['title' => 'Late', '_updated_at' => '2000-01-01T00:00:00.000Z'])->assertOk();

    expect($post->fresh()?->title)->toBe('Late');
});

it('fails boot and shows one FAIL line in doctor when the traffic cop is on a model without updated_at', function () {
    Lathe::registerResources(StampFreeResource::class);

    expect(fn () => Lathe::resources()->manifest())->toThrow(InvalidConfigurationException::class, StampFreeResource::class);

    Artisan::call('lathe:doctor');
    $fails = array_values(array_filter(explode("\n", Artisan::output()), fn (string $line) => str_contains($line, 'FAIL') && str_contains($line, 'trafficCop')));

    expect($fails)->toHaveCount(1)->and($fails[0])->toContain('StampFreeResource');
});
