<?php

namespace Lathe\Tests\Feature\Field\Types;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use InvalidArgumentException;
use Lathe\Field\AudioField;
use Lathe\Field\Avatar;
use Lathe\Field\Contracts\Cover;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Field\GravatarField;
use Lathe\Field\ImageField;
use Lathe\Field\UiAvatarField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Workbench\App\Lathe\Resources\Order;
use Workbench\App\Models\Order as OrderModel;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class MediaThing extends Model
{
    protected $table = 'media_things';

    protected $guarded = [];

    public $timestamps = false;
}

class MediaThingResource extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(MediaThing::class)->title('name')->canonical(false)->allowAll();
    }

    public static function uriKey(): string
    {
        return 'media-things';
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Name'),
            Field::image('Photo', 'photo'),
            Field::image('Banner', 'banner')->acceptedTypes('image/svg+xml')->maxSize(2),
            Field::audio('Sound', 'sound'),
            Field::file('Manual', 'manual'),
        ];
    }
}

class CoverThingResource extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(MediaThing::class)->title('name')->canonical(false)->allowAll();
    }

    public static function uriKey(): string
    {
        return 'cover-things';
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Name'),
            Field::avatar('Avatar', 'photo'),
            Field::gravatar('Gravatar', 'email'),
        ];
    }
}

class PlainThingResource extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(MediaThing::class)->title('name')->canonical(false)->allowAll();
    }

    public static function uriKey(): string
    {
        return 'plain-things';
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::make('Name'), Field::uiAvatar('Initials', 'name')];
    }
}

function mediaRequest(): LatheRequest
{
    return LatheRequest::createFrom(Request::create('/lathe/api/resources/media-things'));
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Schema::dropIfExists('media_things');
    Schema::create('media_things', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('photo')->nullable();
        $table->string('banner')->nullable();
        $table->string('sound')->nullable();
        $table->string('manual')->nullable();
    });
    Storage::fake('public');
    Gate::define('viewLathe', fn (User $user) => true);
    Lathe::registerResources(MediaThingResource::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('builds the image, audio and file fields from their factories', function () {
    $image = Field::image('Photo');
    $audio = Field::audio('Sound');

    expect($image)->toBeInstanceOf(ImageField::class)
        ->and($image->getComponent())->toBe('image-field')
        ->and($audio)->toBeInstanceOf(AudioField::class)
        ->and($audio->getComponent())->toBe('audio-field');
});

it('image_field_accepts_raster_images_and_refuses_svg', function () {
    $start = fn (string $name, string $mime) => $this->postJson('/lathe/api/resources/media-things/uploads/photo', ['name' => $name, 'size' => 10, 'mime' => $mime]);

    foreach (['a.jpg' => 'image/jpeg', 'a.jpeg' => 'image/jpeg', 'a.png' => 'image/png', 'a.gif' => 'image/gif', 'a.webp' => 'image/webp', 'a.avif' => 'image/avif', 'a.bmp' => 'image/bmp'] as $name => $mime) {
        $start($name, $mime)->assertCreated();
    }

    $start('a.svg', 'image/svg+xml')->assertUnprocessable()->assertJsonValidationErrors('mime');
    $this->postJson('/lathe/api/resources/media-things/uploads/banner', ['name' => 'a.svg', 'size' => 10, 'mime' => 'image/svg+xml'])->assertCreated();
})->group('regression');

it('image_field_sizes_default_to_the_image_limit', function () {
    $mb = 1024 * 1024;

    expect(Field::image('Photo')->getMaxSizeBytes())->toBe(5 * $mb)
        ->and(Field::file('Manual')->getMaxSizeBytes())->toBe(10 * $mb)
        ->and(Field::image('Photo')->maxSize(2)->getMaxSizeBytes())->toBe(2 * $mb)
        ->and(Field::file('Manual')->maxSize(2)->getMaxSizeBytes())->toBe(2 * $mb);

    $this->postJson('/lathe/api/resources/media-things/uploads/photo', ['name' => 'a.png', 'size' => 5 * $mb + 1, 'mime' => 'image/png'])->assertUnprocessable()->assertJsonValidationErrors('size');
    $this->postJson('/lathe/api/resources/media-things/uploads/manual', ['name' => 'a.pdf', 'size' => 5 * $mb + 1, 'mime' => 'application/pdf'])->assertCreated();
    $this->postJson('/lathe/api/resources/media-things/uploads/banner', ['name' => 'a.svg', 'size' => 2 * $mb + 1, 'mime' => 'image/svg+xml'])->assertUnprocessable();
});

it('sizes the image 32 px on the index and 128 px on the detail', function () {
    $schema = Field::image('Photo')->schema(FieldContext::Update, mediaRequest());

    expect($schema['indexWidth'])->toBe(32)
        ->and($schema['detailWidth'])->toBe(128)
        ->and($schema['rounded'])->toBeFalse()
        ->and($schema['squared'])->toBeFalse()
        ->and($schema['aspect'])->toBeNull()
        ->and($schema['maxWidth'])->toBeNull();

    $custom = Field::image('Photo')->maxWidth(800)->detailWidth(64)->indexWidth(48)->aspect('16/9')->schema(FieldContext::Update, mediaRequest());

    expect($custom['maxWidth'])->toBe(800)
        ->and($custom['detailWidth'])->toBe(64)
        ->and($custom['indexWidth'])->toBe(48)
        ->and($custom['aspect'])->toBe('16/9');
});

it('lets rounded and squared exclude each other', function () {
    $rounded = Field::image('Photo')->squared()->rounded();
    $squared = Field::image('Photo')->rounded()->squared();

    expect($rounded->isRounded())->toBeTrue()
        ->and($rounded->isSquared())->toBeFalse()
        ->and($squared->isRounded())->toBeFalse()
        ->and($squared->isSquared())->toBeTrue()
        ->and($squared->jsonSerialize()['squared'])->toBeTrue();
});

it('audio_field_accepts_audio_and_hides_from_the_index', function () {
    $audio = Field::audio('Sound', 'sound');
    $request = mediaRequest();

    expect($audio->getAcceptedTypes())->toBe(['audio/*'])
        ->and($audio->accepts('a.mp3', 'audio/mpeg'))->toBeTrue()
        ->and($audio->accepts('a.png', 'image/png'))->toBeFalse()
        ->and($audio->getMaxSizeBytes())->toBe(10 * 1024 * 1024)
        ->and($audio->isShownOn(FieldContext::Index))->toBeFalse()
        ->and($audio->isShownOn(FieldContext::Detail))->toBeTrue()
        ->and($audio->isShownOn(FieldContext::Update))->toBeTrue()
        ->and($audio->schema(FieldContext::Detail, $request)['preload'])->toBe('auto')
        ->and(Field::audio('Sound')->preload('metadata')->schema(FieldContext::Detail, $request)['preload'])->toBe('metadata')
        ->and(Field::audio('Sound')->preload('none')->schema(FieldContext::Detail, $request)['preload'])->toBe('none')
        ->and(Field::audio('Sound')->preload(fn (LatheRequest $request) => 'none')->schema(FieldContext::Detail, $request)['preload'])->toBe('none');

    $columns = collect(LatheTest::resource('media-things')->index()->assertOk()->json('table.columns'))->pluck('key');
    expect($columns->all())->toContain('photo')->not->toContain('sound');
});

it('refuses a preload strategy outside auto, metadata and none', function () {
    Field::audio('Sound')->preload('eager');
})->throws(InvalidArgumentException::class);

it('no_disk_reads_in_cells', function () {
    $adapter = new LocalFilesystemAdapter(storage_path('framework/testing/disks/spy'));
    $spy = new class(new Filesystem($adapter), $adapter, ['url' => '/storage']) extends FilesystemAdapter
    {
        public int $exists = 0;

        public function exists($path): bool
        {
            $this->exists++;

            return parent::exists($path);
        }
    };
    Storage::set('public', $spy);

    DB::table('media_things')->insert(array_map(fn (int $i) => ['name' => "Row {$i}", 'photo' => "photos/{$i}.png", 'sound' => "sounds/{$i}.mp3", 'manual' => "manuals/{$i}.pdf"], range(1, 100)));

    $rows = LatheTest::resource('media-things')->index(['per_page' => 100])->assertOk()->json('table.rows');
    $cell = $rows[0]['cells']['photo'];

    expect($spy->exists)->toBe(0)
        ->and($cell['value']['path'])->toBe('photos/100.png')
        ->and($cell['value']['preview'])->toBe('/storage/photos/100.png')
        ->and($cell['value']['thumbnail'])->toBe('/storage/photos/100.png');
});

it('users_avatars_are_rounded_and_centered_with_downloads_off', function () {
    $avatar = Field::avatar();
    $schema = $avatar->schema(FieldContext::Detail, mediaRequest());
    $value = $avatar->resolve(new MediaThing(['avatar' => 'avatars/a.png']));

    expect($avatar)->toBeInstanceOf(ImageField::class)
        ->and($avatar->attribute())->toBe('avatar')
        ->and($schema['label'])->toBe('Avatar')
        ->and($schema['rounded'])->toBeTrue()
        ->and($schema['textAlign'])->toBe('center')
        ->and($value['downloadable'])->toBeFalse();
});

it('uiavatar_sends_initials_and_style_and_no_url', function () {
    $field = Field::uiAvatar('Initials', 'name');
    $record = new MediaThing(['name' => 'Admin User Extra']);
    $schema = $field->schema(FieldContext::Detail, mediaRequest());
    $value = $field->resolve($record);

    expect($field)->toBeInstanceOf(UiAvatarField::class)
        ->and($field->getComponent())->toBe('ui-avatar-field')
        ->and($field->isShownOn(FieldContext::Update))->toBeFalse()
        ->and($schema['computed'])->toBeTrue()
        ->and($value)->toBe([
            'url' => null,
            'initials' => 'AU',
            'style' => ['fontSize' => 0.5, 'color' => '7F9CF5', 'background' => 'EBF4FF', 'bold' => false],
            'rounded' => true,
        ])
        ->and(json_encode($value))->not->toContain('http')
        ->and(Field::uiAvatar()->getComponent())->toBe('ui-avatar-field');

    $styled = Field::uiAvatar('Initials')->fontSize(0.8)->avatarColor('#112233')->backgroundColor('abcdef')->bold()->resolve(new MediaThing(['name' => 'solo']));

    expect($styled['initials'])->toBe('S')
        ->and($styled['style'])->toBe(['fontSize' => 0.8, 'color' => '112233', 'background' => 'ABCDEF', 'bold' => true]);
});

it('keeps the uiavatar font size between 0.1 and 1 and the colors hex', function () {
    expect(Field::uiAvatar()->fontSize(5)->resolve(new MediaThing(['name' => 'a']))['style']['fontSize'])->toBe(1.0)
        ->and(Field::uiAvatar()->fontSize(0)->resolve(new MediaThing(['name' => 'a']))['style']['fontSize'])->toBe(0.1);

    Field::uiAvatar()->avatarColor('red;}');
})->throws(InvalidArgumentException::class);

it('gravatar_builds_the_url_on_the_server', function () {
    Http::preventStrayRequests();

    $field = Field::gravatar();
    $hash = hash('sha256', 'admin@lathephp.com');
    $value = $field->resolve(new MediaThing(['email' => '  Admin@LathePHP.com ']));

    expect($field)->toBeInstanceOf(GravatarField::class)
        ->and($field->getComponent())->toBe('ui-avatar-field')
        ->and($field->isShownOn(FieldContext::Update))->toBeFalse()
        ->and($value['url'])->toBe("https://www.gravatar.com/avatar/{$hash}?s=300")
        ->and($value['initials'])->toBe('A')
        ->and($value['rounded'])->toBeTrue();

    $empty = $field->resolve(new MediaThing(['email' => '']));

    expect($empty['url'])->toBeNull()
        ->and($empty['initials'])->toBe('?');
});

it('csp_image_sources_names_gravatar_only_when_a_field_exists', function () {
    expect(Lathe::cspImageSources())->toBe([]);

    Lathe::registerResources(PlainThingResource::class);
    expect(Lathe::cspImageSources())->toBe([]);

    Lathe::registerResources(CoverThingResource::class);
    expect(Lathe::cspImageSources())->toBe(['https://www.gravatar.com']);
});

it('resource_avatar_takes_the_first_cover_field', function () {
    $resource = new CoverThingResource(new MediaThing(['name' => 'Admin User', 'photo' => 'avatars/a.png', 'email' => 'a@b.c']));
    $avatar = $resource->resolveAvatar();

    expect($avatar)->toBeInstanceOf(Avatar::class)
        ->and($avatar->url)->toBe('/storage/avatars/a.png')
        ->and($avatar->initials)->toBeNull()
        ->and($avatar->rounded)->toBeTrue()
        ->and($avatar->style)->toBe([]);

    $bare = (new CoverThingResource(new MediaThing(['name' => 'Admin User'])))->resolveAvatar();

    expect($bare?->url)->toBeNull()
        ->and($bare?->initials)->toBe('AU');

    expect((new Order(new OrderModel))->resolveAvatar())->toBeNull();
});

it('gives the uiavatar field the resource avatar', function () {
    $avatar = (new PlainThingResource(new MediaThing(['name' => 'Editor User'])))->resolveAvatar();

    expect($avatar?->url)->toBeNull()
        ->and($avatar?->initials)->toBe('EU')
        ->and($avatar?->style)->toBe(['fontSize' => 0.5, 'color' => '7F9CF5', 'background' => 'EBF4FF', 'bold' => false]);
});

it('avatar_has_a_stable_response_shape', function () {
    $avatar = new Avatar('https://x.test/a.png', 'AU', ['bold' => true], true);

    expect($avatar->jsonSerialize())->toBe(['url' => 'https://x.test/a.png', 'initials' => 'AU', 'style' => ['bold' => true], 'rounded' => true]);
});

it('sends the avatar in the detail, peek and preview payloads', function () {
    Lathe::registerResources(CoverThingResource::class);
    $id = DB::table('media_things')->insertGetId(['name' => 'Admin User', 'photo' => 'avatars/a.png', 'email' => 'a@b.c']);
    $tester = LatheTest::resource('cover-things');
    $expected = ['url' => '/storage/avatars/a.png', 'initials' => null, 'style' => [], 'rounded' => true];

    expect($tester->peek($id)->assertOk()->json('avatar'))->toBe($expected)
        ->and($tester->preview($id)->assertOk()->json('avatar'))->toBe($expected);

    $this->get("/lathe/resources/cover-things/{$id}")->assertInertia(fn (AssertableInertia $page) => $page->where('record.avatar', $expected));
});

it('implements the cover contract on the image and avatar fields', function () {
    expect(Field::image('Photo'))->toBeInstanceOf(Cover::class)
        ->and(Field::uiAvatar())->toBeInstanceOf(Cover::class)
        ->and(Field::image('Photo')->resolveThumbnailUrl(new MediaThing(['photo' => 'a.png'])))->toBe('/storage/a.png')
        ->and(Field::image('Photo')->resolveThumbnailUrl(new MediaThing))->toBeNull();
});

it('puts the download address and the deletable flag on a file field of the detail page', function () {
    $id = DB::table('media_things')->insertGetId(['name' => 'Doc', 'manual' => 'manuals/a.pdf', 'photo' => 'photos/a.png']);

    $this->get("/lathe/resources/media-things/{$id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('record.fields', function ($fields) use ($id) {
            $fields = collect($fields)->keyBy('key');

            return $fields['manual']['download'] === "/lathe/api/resources/media-things/{$id}/download/manual"
                && $fields['manual']['deletable'] === true
                && $fields['manual']['display'] === 'a.pdf'
                && $fields['photo']['download'] === "/lathe/api/resources/media-things/{$id}/download/photo";
        }));
});
