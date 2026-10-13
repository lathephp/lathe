<?php

namespace Lathe\Tests\Feature\Field\Types;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Field\IdField;
use Lathe\Field\PasswordField;
use Lathe\Field\TextareaField;
use Lathe\Field\TextField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Support\SafeHtml;
use Lathe\Testing\LatheTest;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class TextSample extends Model
{
    protected $table = 'text_samples';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['title' => 'array'];
}

class TextSamples extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(TextSample::class)->title('name')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::id(),
            Field::text('Name')->required(),
            Field::text('Short')->maxlength(10)->enforceMaxlength(),
            Field::text('Title')->translatable(['en', 'fr'])->required(),
            Field::hidden('Locale', 'locale'),
            Field::password('Password')->rules('confirmed'),
            Field::passwordConfirmation('Password confirmation'),
        ];
    }
}

function textRequest(): LatheRequest
{
    return LatheRequest::createFrom(Request::create('/lathe/api/resources/text-samples'));
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Schema::create('text_samples', function ($table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('short')->nullable();
        $table->json('title')->nullable();
        $table->string('locale')->nullable();
        $table->string('password')->nullable();
        $table->text('notes')->nullable();
    });
    Gate::define('viewLathe', fn (User $user) => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    Lathe::registerResources(TextSamples::class);
});

/** @return list<string> */
function textContexts(Field $field): array
{
    $shown = array_filter(FieldContext::cases(), fn (FieldContext $context) => $field->isShownOn($context, textRequest()));

    return array_values(array_map(fn (FieldContext $context) => $context->value, $shown));
}

it('builds each text factory with its class, component and contexts', function (Field $field, string $class, string $component, array $contexts) {
    expect($field)->toBeInstanceOf($class)
        ->and($field->getComponent())->toBe($component)
        ->and(textContexts($field))->toBe($contexts);
})->with([
    'text' => [fn () => Field::text('Name'), TextField::class, 'text-field', ['index', 'detail', 'create', 'update']],
    'textarea' => [fn () => Field::textarea('Bio'), TextareaField::class, 'textarea-field', ['detail', 'create', 'update']],
    'email' => [fn () => Field::email(), TextField::class, 'email-field', ['index', 'detail', 'create', 'update']],
    'url' => [fn () => Field::url('Site'), TextField::class, 'url-field', ['index', 'detail', 'create', 'update']],
    'color' => [fn () => Field::color('Tint'), Field::class, 'color-field', ['index', 'detail', 'create', 'update']],
    'password' => [fn () => Field::password(), PasswordField::class, 'password-field', ['index', 'detail', 'create', 'update']],
    'passwordConfirmation' => [fn () => Field::passwordConfirmation('Confirm'), PasswordField::class, 'password-field', ['create', 'update']],
    'hidden' => [fn () => Field::hidden('Locale', 'locale'), Field::class, 'hidden-field', ['create', 'update']],
    'heading' => [fn () => Field::heading('Billing'), Field::class, 'heading-field', ['detail', 'create', 'update']],
    'id' => [fn () => Field::id(), IdField::class, 'id-field', ['index', 'detail']],
    'IdField::hidden' => [fn () => IdField::hidden(), IdField::class, 'id-field', []],
]);

it('builds a computed form-hidden field from a Closure attribute', function (Field $field) {
    expect($field->isComputed())->toBeTrue()
        ->and($field->isShownOn(FieldContext::Create, textRequest()))->toBeFalse()
        ->and($field->isShownOn(FieldContext::Update, textRequest()))->toBeFalse();
})->with([
    'text' => [fn () => Field::text('Shout', fn ($record) => 'x')],
    'email' => [fn () => Field::email('Mail', fn ($record) => 'x')],
    'textarea' => [fn () => Field::textarea('Long', fn ($record) => 'x')],
]);

it('defaults a textarea to 4 rows and takes rows()', function () {
    expect(Field::textarea('Bio')->jsonSerialize()['rows'])->toBe(4)
        ->and(Field::textarea('Bio')->rows(8)->jsonSerialize()['rows'])->toBe(8);
});

it('sets the email and url autocomplete and centers url', function () {
    expect(Field::email()->jsonSerialize()['autocomplete'])->toBe('email')
        ->and(Field::url('Site')->jsonSerialize())->toMatchArray(['autocomplete' => 'url', 'textAlign' => 'center'])
        ->and(Field::color('Tint')->jsonSerialize()['textAlign'])->toBe('center');
});

it('sends at most 50 suggestions and runs a Closure once per schema', function () {
    $calls = 0;
    $field = Field::text('Tag')->suggestions(function () use (&$calls) {
        $calls++;

        return array_map(fn (int $n) => "tag {$n}", range(1, 80));
    });

    $schema = $field->schema(FieldContext::Create, textRequest());

    expect($schema['suggestions'])->toHaveCount(50)
        ->and($schema['suggestions'][49])->toBe('tag 50')
        ->and($calls)->toBe(1)
        ->and(Field::text('Tag')->suggestions(['a', 'b'])->jsonSerialize()['suggestions'])->toBe(['a', 'b']);
});

it('sends copyable, maxlength and enforceMaxlength', function () {
    expect(Field::text('Short')->copyable()->maxlength(10)->enforceMaxlength()->jsonSerialize())
        ->toMatchArray(['copyable' => true, 'maxlength' => 10, 'enforceMaxlength' => true])
        ->and(Field::text('Short')->jsonSerialize())->toMatchArray(['copyable' => false, 'maxlength' => null, 'enforceMaxlength' => false]);
});

it('rejects 11 characters on a maxlength(10) field on the server', function () {
    $resource = LatheTest::resource('text-samples');
    $ok = ['name' => 'A', 'title' => ['en' => 'x', 'fr' => 'y']];

    $resource->store([...$ok, 'short' => str_repeat('a', 11)])->assertStatus(422)->assertJsonValidationErrors(['short']);
    $resource->store([...$ok, 'short' => str_repeat('a', 10)])->assertSuccessful();
});

it('keeps translatable state per locale and keys errors by locale', function () {
    $resource = LatheTest::resource('text-samples');

    $resource->store(['name' => 'A', 'title' => ['en' => 'Hello', 'fr' => 'Bonjour', 'de' => 'Hallo']])->assertSuccessful();

    expect(TextSample::query()->latest('id')->firstOrFail()->title)->toBe(['en' => 'Hello', 'fr' => 'Bonjour']);

    $resource->store(['name' => 'B', 'title' => ['en' => 'Hello', 'fr' => '']])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title.fr'])
        ->assertJsonMissingValidationErrors(['title.en']);

    expect(Field::text('Title')->translatable(['en', 'fr'])->jsonSerialize()['translatable'])->toBe(['en', 'fr']);

    $sample = TextSample::create(['title' => ['en' => 'Hi']]);
    expect(Field::text('Title')->translatable(['en', 'fr'])->resolve($sample))->toBe(['en' => 'Hi', 'fr' => '']);
});

it('hashes a password, keeps the hash on an empty one and never stores the confirmation', function () {
    $resource = LatheTest::resource('text-samples');
    $ok = ['name' => 'A', 'title' => ['en' => 'x', 'fr' => 'y']];

    $id = $resource->store([...$ok, 'password' => 'secret-123', 'password_confirmation' => 'secret-123'])->assertSuccessful()->json('id');
    $hash = TextSample::findOrFail($id)->password;

    expect(Hash::check('secret-123', $hash))->toBeTrue()
        ->and($hash)->not->toBe('secret-123');

    $resource->update($id, [...$ok, 'password' => '', 'password_confirmation' => ''])->assertSuccessful();
    expect(TextSample::findOrFail($id)->password)->toBe($hash);

    $resource->update($id, [...$ok, 'password' => 'other-pass-9', 'password_confirmation' => 'other-pass-9'])->assertSuccessful();
    expect(Hash::check('other-pass-9', TextSample::findOrFail($id)->password))->toBeTrue()
        ->and(TextSample::findOrFail($id)->getAttributes())->not->toHaveKey('password_confirmation');
});

it('shows the confirmation message under password on a mismatch', function () {
    LatheTest::resource('text-samples')
        ->store(['name' => 'A', 'title' => ['en' => 'x', 'fr' => 'y'], 'password' => 'secret-123', 'password_confirmation' => 'nope'])
        ->assertStatus(422)
        ->assertJsonPath('errors.password.0', 'The password field confirmation does not match.');
});

it('resolves a password to an empty string and never its hash', function () {
    $sample = TextSample::create(['password' => Hash::make('secret')]);

    expect(Field::password('Password')->resolve($sample))->toBe('')
        ->and(Field::password('Password')->cell($sample)['value'])->toBe('');
});

it('submits a hidden field value with no control', function () {
    LatheTest::resource('text-samples')
        ->store(['name' => 'A', 'title' => ['en' => 'x', 'fr' => 'y'], 'locale' => 'fr'])->assertSuccessful();

    expect(TextSample::query()->latest('id')->firstOrFail()->locale)->toBe('fr');
});

it('renders a heading through the sanitizer', function () {
    $schema = Field::heading('<b>Billing</b><script>x</script>')->schema(FieldContext::Create, textRequest());

    expect(json_encode($schema['content']))->toBe('{"__html":"<b>Billing<\/b>"}');
});

it('sends asHtml values as SafeHtml cleaned by the sanitizer', function (string $payload, string $clean) {
    $sample = TextSample::create(['name' => $payload]);

    $cell = Field::text('Name')->asHtml()->cell($sample);

    expect($cell['value'])->toBeInstanceOf(SafeHtml::class)
        ->and($cell['value']->toHtml())->toBe($clean);
})->with('xss_payloads');

it('shows asEncodedHtml as text', function () {
    $field = Field::text('Name')->asEncodedHtml();
    $cell = $field->cell(TextSample::create(['name' => '<b>x</b>']));

    expect($cell['value'])->toBe('<b>x</b>')
        ->and($field->jsonSerialize()['asEncodedHtml'])->toBeTrue();
});

it('sends alwaysShow and the shouldShow answer for the textarea collapse', function () {
    $sample = TextSample::create(['notes' => 'x']);

    expect(Field::textarea('Notes')->alwaysShow()->jsonSerialize()['alwaysShow'])->toBeTrue()
        ->and(Field::textarea('Notes')->jsonSerialize()['alwaysShow'])->toBeFalse()
        ->and(Field::textarea('Notes')->shouldShow(fn ($record) => $record->notes === 'x')->schema(FieldContext::Detail, textRequest(), $sample)['expanded'])->toBeTrue()
        ->and(Field::textarea('Notes')->shouldShow(fn () => false)->schema(FieldContext::Detail, textRequest(), $sample)['expanded'])->toBeFalse();
});
