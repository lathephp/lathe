<?php

namespace Lathe\Tests\Feature\Resources;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Lathe\Events\ResourceCreated;
use Lathe\Events\ResourceUpdated;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use Lathe\Tests\Fixtures\Models\StringThing;
use Lathe\Tests\Fixtures\Models\UlidThing;
use Lathe\Tests\Fixtures\Models\UuidThing;
use RuntimeException;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

/** Collects each step of a write with the transaction level it ran at. */
class WriteFlowLog
{
    /** @var list<string> */
    public static array $entries = [];

    public static ?string $throwIn = null;

    public static function add(string $step): void
    {
        self::$entries[] = $step.'@'.DB::transactionLevel();

        if (self::$throwIn === $step) {
            throw new RuntimeException("{$step} failed");
        }
    }
}

class LoggedPost extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Post::class)->title('title')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Title')->required()->rules('max:200')->updateRules('min:2')->creationRules('min:3')
                ->messages(['min' => 'Too short.']),
            Field::make('Status')->required()->fillUsing(function (LatheRequest $request, Model $model, string $attribute) {
                WriteFlowLog::add('fill');
                $model->setAttribute($attribute, $request->input($attribute));

                return function () use ($model) {
                    WriteFlowLog::add('callback');
                    DB::table('posts')->where('id', $model->getKey())->update(['body' => 'from callback']);
                };
            }),
            Field::make('Body')->validationAttribute('post body'),
            Field::make('Secret', 'deleted_at')->canSee(fn () => false),
        ];
    }

    public static function defaultAttributes(LatheRequest $request): array
    {
        return ['body' => 'default body'];
    }

    public static function editQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query->where('status', '!=', 'hidden');
    }

    public static function afterValidation(LatheRequest $request, Validator $validator): void
    {
        WriteFlowLog::add('afterValidation');

        if ($request->input('title') === 'refuse') {
            $validator->errors()->add('title', 'Refused.');
        }
    }

    public static function afterCreationValidation(LatheRequest $request, Validator $validator): void
    {
        WriteFlowLog::add('afterCreationValidation');
    }

    public static function afterUpdateValidation(LatheRequest $request, Validator $validator): void
    {
        WriteFlowLog::add('afterUpdateValidation');
    }

    public static function beforeCreate(LatheRequest $request, Model $model): void
    {
        WriteFlowLog::add('beforeCreate');
    }

    public static function afterCreate(LatheRequest $request, Model $model): void
    {
        WriteFlowLog::add('afterCreate:'.Post::query()->whereKey($model->getKey())->value('body'));
    }

    public static function beforeUpdate(LatheRequest $request, Model $model): void
    {
        WriteFlowLog::add('beforeUpdate');
    }

    public static function afterUpdate(LatheRequest $request, Model $model): void
    {
        WriteFlowLog::add('afterUpdate');
    }
}

class RedirectingPost extends LoggedPost
{
    public static function redirectAfterCreate(LatheRequest $request, Resource $resource): string
    {
        return 'posts';
    }

    public static function redirectAfterUpdate(LatheRequest $request, Resource $resource): string
    {
        return 'dashboard';
    }
}

class IntThing extends Model
{
    protected $table = 'int_things';

    protected $guarded = [];
}

class KeyedThing extends Resource
{
    /** @var class-string<Model> */
    public static string $modelClass = UuidThing::class;

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(static::$modelClass)->title('name')->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::make('Name')->required()];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => true);
    Lathe::registerResources(LoggedPost::class, RedirectingPost::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    WriteFlowLog::$entries = [];
    WriteFlowLog::$throwIn = null;
    Event::listen(ResourceCreated::class, fn () => WriteFlowLog::add('ResourceCreated'));
    Event::listen(ResourceUpdated::class, fn () => WriteFlowLog::add('ResourceUpdated'));
});

it('runs a create through its hooks in order, the event inside the transaction', function () {
    LatheTest::resource('logged-posts')->store(['title' => 'Hello', 'status' => 'draft'])->assertCreated();

    expect(WriteFlowLog::$entries)->toBe([
        'afterValidation@0', 'afterCreationValidation@0', 'beforeCreate@1', 'fill@1', 'callback@1', 'ResourceCreated@1', 'afterCreate:from callback@0',
    ]);
});

it('runs an update through its hooks in order, the event inside the transaction', function () {
    $post = Post::query()->whereNull('deleted_at')->firstOrFail();

    LatheTest::resource('logged-posts')->update($post->id, ['title' => 'Changed', 'status' => 'draft'])->assertOk();

    expect(WriteFlowLog::$entries)->toBe([
        'afterValidation@0', 'afterUpdateValidation@0', 'beforeUpdate@1', 'fill@1', 'callback@1', 'ResourceUpdated@1', 'afterUpdate@0',
    ])->and($post->fresh()?->title)->toBe('Changed');
});

it('carries the original and the changes in ResourceUpdated', function () {
    $post = Post::query()->whereNull('deleted_at')->firstOrFail();
    $seen = null;
    Event::listen(ResourceUpdated::class, function (ResourceUpdated $event) use (&$seen) {
        $seen = $event;
    });

    LatheTest::resource('logged-posts')->update($post->id, ['title' => 'Changed', 'status' => $post->status])->assertOk();

    expect($seen?->original['title'])->toBe($post->title)
        ->and($seen?->changes['title'])->toBe('Changed')
        ->and($seen?->resource)->toBe(LoggedPost::class);
});

it('rolls the create back when a step inside the transaction throws', function (string $step) {
    WriteFlowLog::$throwIn = $step;
    $count = Post::withTrashed()->count();

    LatheTest::resource('logged-posts')->store(['title' => 'Hello', 'status' => 'draft'])->assertStatus(500);

    expect(Post::withTrashed()->count())->toBe($count)
        ->and(Post::withTrashed()->where('body', 'from callback')->exists())->toBeFalse();
})->with(['beforeCreate', 'ResourceCreated']);

it('answers the create with the redirect to the new record, or the overridden one', function () {
    $response = LatheTest::resource('logged-posts')->store(['title' => 'Hello', 'status' => 'draft'])->assertCreated();

    expect($response->json('redirect'))->toBe('logged-posts/201')
        ->and(LatheTest::resource('redirecting-posts')->store(['title' => 'Hello', 'status' => 'draft'])->json('redirect'))->toBe('posts')
        ->and(LatheTest::resource('redirecting-posts')->update(201, ['title' => 'Again', 'status' => 'draft'])->json('redirect'))->toBe('dashboard')
        ->and(LatheTest::resource('logged-posts')->update(201, ['title' => 'Again', 'status' => 'draft'])->json('redirect'))->toBe('logged-posts/201');
});

it('starts a new model from defaultAttributes', function () {
    WriteFlowLog::$throwIn = null;
    Event::forget(ResourceCreated::class);
    $id = LatheTest::resource('redirecting-posts')->store(['title' => 'Hello', 'status' => 'x'])->json('id');

    // The fill callback overwrites body after save, so check the value the insert carried.
    expect(Post::query()->whereKey($id)->value('body'))->toBe('from callback');
    expect(WriteFlowLog::$entries)->toContain('afterCreate:from callback@0');
});

it('loads every update through editQuery and leaves unknown and hidden keys alone', function () {
    $hidden = Post::query()->whereNull('deleted_at')->firstOrFail();
    $hidden->forceFill(['status' => 'hidden'])->save();
    $post = Post::query()->whereNull('deleted_at')->where('status', '!=', 'hidden')->firstOrFail();

    LatheTest::resource('logged-posts')->update($hidden->id, ['title' => 'Nope', 'status' => 'draft'])->assertNotFound();

    LatheTest::resource('logged-posts')->update($post->id, [
        'title' => 'Fine', 'status' => 'draft', 'created_at' => '2000-01-01 00:00:00', 'deleted_at' => '2000-01-01 00:00:00',
    ])->assertOk();

    $fresh = Post::withTrashed()->findOrFail($post->id);

    expect($hidden->fresh()?->title)->not->toBe('Nope')
        ->and($fresh->deleted_at)->toBeNull()
        ->and($fresh->created_at?->year)->not->toBe(2000);
});

it('refuses an invalid write with 422, message and errors, and writes nothing', function () {
    $count = Post::withTrashed()->count();

    $response = LatheTest::resource('logged-posts')->store(['title' => '', 'status' => 'draft'])->assertUnprocessable();

    expect($response->json('message'))->toBeString()
        ->and($response->json('errors.title'))->toBe(['The Title field is required.'])
        ->and(Post::withTrashed()->count())->toBe($count)
        ->and(LatheTest::resource('logged-posts')->store(['title' => 'refuse', 'status' => 'draft'])->assertUnprocessable()->json('errors.title'))->toBe(['Refused.']);
});

it('applies rules, creation and update rules, messages and the validation attribute', function () {
    $post = Post::query()->whereNull('deleted_at')->firstOrFail();

    expect(LatheTest::resource('logged-posts')->store(['title' => 'ab', 'status' => 'd'])->assertUnprocessable()->json('errors.title'))->toBe(['Too short.'])
        ->and(LatheTest::resource('logged-posts')->update($post->id, ['title' => 'ab', 'status' => 'd'])->assertOk()->json('id'))->toBe($post->id)
        ->and(LatheTest::resource('logged-posts')->update($post->id, ['title' => 'a', 'status' => 'd'])->assertUnprocessable()->json('errors.title'))->toBe(['Too short.'])
        ->and(LatheTest::resource('logged-posts')->store(['title' => str_repeat('a', 201), 'status' => 'd'])->assertUnprocessable()->json('errors.title.0'))->toContain('200');
});

it('replaces {{resourceId}} with the record key so a unique rule ignores the record itself', function () {
    $post = Post::query()->whereNull('deleted_at')->firstOrFail();
    $field = Field::make('Title')->rules('unique:posts,title,{{resourceId}}');

    expect($field->getRules(FieldContext::Update, null, $post))->toBe(['title' => ["unique:posts,title,{$post->id}"]]);
});

it('creates, updates and deletes a record whatever its key type', function (string $model, string $type) {
    Schema::create((new $model)->getTable(), function (Blueprint $table) use ($type) {
        $type === 'id' ? $table->id() : $table->{$type}('id')->primary();
        $table->string('name');
        $table->timestamps();
    });
    KeyedThing::$modelClass = $model;
    Lathe::registerResources(KeyedThing::class);
    $data = ['name' => 'First'];

    if ($model === StringThing::class) {
        $model::query()->create(['id' => 'sku-1', 'name' => 'First']);
        $id = 'sku-1';
    } else {
        $id = LatheTest::resource('keyed-things')->store($data)->assertCreated()->json('id');
    }

    LatheTest::resource('keyed-things')->update($id, ['name' => 'Second'])->assertOk();
    expect($model::query()->findOrFail($id)->getAttribute('name'))->toBe('Second');

    LatheTest::resource('keyed-things')->delete($id)->assertOk();
    expect($model::query()->find($id))->toBeNull();
})->with([
    'int' => [IntThing::class, 'id'],
    'uuid' => [UuidThing::class, 'uuid'],
    'ulid' => [UlidThing::class, 'ulid'],
    'custom string' => [StringThing::class, 'string'],
]);
