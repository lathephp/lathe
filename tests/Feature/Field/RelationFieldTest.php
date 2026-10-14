<?php

use Illuminate\Support\Str;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Field\Relations\BelongsToField;
use Lathe\Field\Relations\BelongsToManyField;
use Lathe\Field\Relations\HasManyField;
use Lathe\Field\Relations\HasManyThroughField;
use Lathe\Field\Relations\HasOneField;
use Lathe\Field\Relations\HasOneThroughField;
use Lathe\Field\Relations\MorphedByManyField;
use Lathe\Field\Relations\MorphManyField;
use Lathe\Field\Relations\MorphOneField;
use Lathe\Field\Relations\MorphToField;
use Lathe\Field\Relations\MorphToManyField;
use Lathe\Field\Relations\RelationField;
use Lathe\Field\Relations\TagField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Lathe\Resources\Customer;
use Workbench\App\Lathe\Resources\Order;
use Workbench\App\Lathe\Resources\Post;
use Workbench\App\Lathe\Resources\Product;
use Workbench\App\Models\Order as OrderModel;

/**
 * @return list<string>
 */
function relationContexts(Field $field): array
{
    return array_values(array_map(fn (FieldContext $context) => $context->value, array_filter(FieldContext::cases(), fn (FieldContext $context) => $field->isShownOn($context))));
}

it('builds each relation factory with its class, kind, component and contexts', function (Closure $make, string $class, string $kind, array $contexts, bool $nullable, bool $readonly) {
    $field = $make();

    expect($field)->toBeInstanceOf($class)
        ->and($field->kind())->toBe($kind)
        ->and($field->getComponent())->toBe($field->kind() === 'hasOneOfMany' ? 'has-one-field' : Str::kebab($kind).'-field')
        ->and(relationContexts($field))->toBe($contexts)
        ->and($field->isNullable())->toBe($nullable)
        ->and($field->isReadonly())->toBe($readonly);
})->with([
    'belongsTo' => [fn () => Field::belongsTo('Customer'), BelongsToField::class, 'belongsTo', ['index', 'detail', 'create', 'update'], false, false],
    'morphTo' => [fn () => Field::morphTo('Commentable'), MorphToField::class, 'morphTo', ['index', 'detail', 'create', 'update'], false, false],
    'tag' => [fn () => Field::tag('Tags'), TagField::class, 'tag', ['index', 'detail', 'create', 'update'], false, false],
    'hasMany' => [fn () => Field::hasMany('Items'), HasManyField::class, 'hasMany', ['detail'], false, false],
    'morphMany' => [fn () => Field::morphMany('Comments'), MorphManyField::class, 'morphMany', ['detail'], false, false],
    'belongsToMany' => [fn () => Field::belongsToMany('Roles'), BelongsToManyField::class, 'belongsToMany', ['detail'], false, false],
    'morphToMany' => [fn () => Field::morphToMany('Tags'), MorphToManyField::class, 'morphToMany', ['detail'], false, false],
    'morphedByMany' => [fn () => Field::morphedByMany('Products'), MorphedByManyField::class, 'morphedByMany', ['detail'], false, false],
    'hasOneThrough' => [fn () => Field::hasOneThrough('Owner'), HasOneThroughField::class, 'hasOneThrough', ['detail'], false, false],
    'hasManyThrough' => [fn () => Field::hasManyThrough('Deployments'), HasManyThroughField::class, 'hasManyThrough', ['detail'], false, false],
    'hasOne' => [fn () => Field::hasOne('Warranty'), HasOneField::class, 'hasOne', ['detail', 'create', 'update'], true, false],
    'morphOne' => [fn () => Field::morphOne('Image'), MorphOneField::class, 'morphOne', ['detail', 'create', 'update'], true, false],
    'hasOneOfMany' => [fn () => Field::hasOneOfMany('Latest Order'), HasOneField::class, 'hasOneOfMany', ['detail'], true, true],
]);

it('guesses the relation name from the label and reads no backtrace', function () {
    expect(Field::belongsTo('User Profile')->attribute())->toBe('userProfile')
        ->and(Field::belongsTo('Customer')->attribute())->toBe('customer')
        ->and(Field::belongsTo('Buyer', 'customer')->attribute())->toBe('customer');

    $source = (string) file_get_contents(dirname(__DIR__, 3).'/src/Field/Relations/ResourceRelationshipGuesser.php');
    expect($source)->not->toContain('debug_backtrace');
});

it('resolves the related Resource through the canonical Resource of the related model', function () {
    $field = Field::belongsTo('Customer')->ownedBy(Order::class);

    expect($field->relatedResource())->toBe(Customer::class)
        ->and($field->jsonSerialize()['resource'])->toBe('customers');
});

it('lets resource() override the guess', function () {
    $field = Field::belongsTo('Customer')->resource(Product::class)->ownedBy(Order::class);

    expect($field->relatedResource())->toBe(Product::class);
});

it('falls back to App\Lathe\Resources\{StudlySingular} when no model resolves', function () {
    $field = Field::hasMany('Line Widgets');

    expect($field->relatedResourceGuess())->toBe('App\\Lathe\\Resources\\LineWidget');
});

it('binds the owning Resource while building the fields of a context', function () {
    $resource = new class extends Resource
    {
        protected function configure(ResourceDefinition $definition): ResourceDefinition
        {
            return $definition->model(OrderModel::class)->title('number');
        }

        public function fields(LatheRequest $request): array
        {
            return [Field::belongsTo('Customer')];
        }
    };

    $field = $resource->fieldsFor(FieldContext::Detail, LatheRequest::createFrom(request()))->first();

    expect($field)->toBeInstanceOf(BelongsToField::class)
        ->and($field->owner())->toBe($resource::class)
        ->and($field->relatedResource())->toBe(Customer::class);
});

it('defaults the labels to the related Resource and lets them be set', function () {
    $field = Field::hasMany('Orders')->ownedBy(Customer::class);

    expect($field->getSingularLabel())->toBe('Order')
        ->and($field->getPluralLabel())->toBe('Orders');

    $custom = Field::hasMany('Orders')->ownedBy(Customer::class)->singularLabel('Purchase')->pluralLabel('Purchases');

    expect($custom->jsonSerialize())->toMatchArray(['singularLabel' => 'Purchase', 'pluralLabel' => 'Purchases']);
});

it('stores and sends the shared relation options', function () {
    $field = Field::belongsTo('Customer')
        ->withSubtitles()
        ->creatable()
        ->modalSize('lg')
        ->searchable()
        ->debounce(250)
        ->withTrashed()
        ->inverse('orders')
        ->reorderAssociatables(false)
        ->collapsed()
        ->perPageOptions([10, 25])
        ->withCount()
        ->noPeeking();

    expect($field->jsonSerialize())->toMatchArray([
        'kind' => 'belongsTo',
        'relationship' => 'customer',
        'withSubtitles' => true,
        // creatable is true on the wire only for a user who may create and add; see BelongsToSaveTest.
        'modalSize' => 'lg',
        'searchable' => true,
        'debounce' => 250,
        'withTrashed' => true,
        'collapsible' => true,
        'collapsed' => true,
        'perPageOptions' => [10, 25],
        'withCount' => true,
    ])->and($field->getInverse())->toBe('orders')
        ->and($field->reordersAssociatables())->toBeFalse()
        ->and($field->isPeekable(LatheRequest::createFrom(request())))->toBeFalse();
});

it('defaults debounce to 500 ms, per-page to the panel defaults, peeking on and reordering on', function () {
    $field = Field::hasMany('Items');

    expect($field->jsonSerialize())->toMatchArray(['debounce' => 500, 'withTrashed' => false, 'creatable' => false, 'collapsible' => false])
        ->and($field->reordersAssociatables())->toBeTrue()
        ->and($field->isPeekable(LatheRequest::createFrom(request())))->toBeTrue();
});

it('accepts modal sizes sm to 7xl only', function () {
    foreach (['sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl', '6xl', '7xl'] as $size) {
        expect(Field::belongsTo('Customer')->modalSize($size)->jsonSerialize()['modalSize'])->toBe($size);
    }

    Field::belongsTo('Customer')->modalSize('8xl');
})->throws(InvalidArgumentException::class);

it('rejects a per-page option above 100', function () {
    Field::hasMany('Items')->perPageOptions([200]);
})->throws(InvalidArgumentException::class);

it('keeps the relatable query and display closures', function () {
    $query = fn ($request, $builder) => $builder;
    $display = fn ($model) => 'x';
    $field = Field::belongsTo('Customer')->relatableQuery($query)->display($display);

    expect($field->getRelatableQuery())->toBe($query)
        ->and($field->getDisplayCallback())->toBe($display);
});

it('stores the per-kind options', function () {
    $filled = fn () => null;
    $belongsTo = Field::belongsTo('Customer')->viewable(false)->filled($filled);
    expect($belongsTo->isViewable())->toBeFalse()
        ->and($belongsTo->getFilledCallback())->toBe($filled)
        ->and(Field::belongsTo('Customer')->isViewable())->toBeTrue()
        ->and(Field::belongsTo('Customer')->withTrashed()->withoutTrashed()->jsonSerialize()['withTrashed'])->toBeFalse();

    $morph = Field::morphTo('Commentable')->types([Post::class, Product::class])->defaultResource(Post::class)->displaysWithTrashed(false);
    expect($morph->getTypes())->toBe([Post::class, Product::class])
        ->and($morph->jsonSerialize())->toMatchArray(['types' => [['value' => 'posts', 'label' => 'Post'], ['value' => 'products', 'label' => 'Product']], 'defaultType' => 'posts', 'withTrashed' => false]);

    $when = fn () => true;
    $hasOne = Field::hasOne('Warranty')->alreadyFilledWhen($when);
    expect($hasOne->getAlreadyFilledWhen())->toBe($when)
        ->and($hasOne->isOfMany())->toBeFalse()
        ->and(Field::hasOne('Latest Order')->ofMany()->isOfMany())->toBeTrue();

    $tag = Field::tag('Tags')->displayAsList()->preload()->withPreview();
    // preload is true on the wire only once a small related table is known; see TagFieldTest.
    expect($tag->jsonSerialize())->toMatchArray(['display' => 'list', 'withPreview' => true])
        ->and(Field::tag('Tags')->jsonSerialize())->toMatchArray(['display' => 'group', 'preload' => false, 'withPreview' => false]);
});

it('stores pivot fields, pivot actions and the duplicate rule on the pivot kinds', function (Closure $make) {
    $fields = fn () => [Field::number('Position')];
    $field = $make()->pivotFields($fields)->pivotActions(['Tag Actions']);

    expect($field->getPivotFields())->toBe($fields)
        ->and($field->getPivotActions())->toBe(['Tag Actions'])
        ->and($field->allowsDuplicateRelations())->toBeFalse()
        ->and($make()->allowDuplicateRelations()->allowsDuplicateRelations())->toBeTrue()
        ->and($make()->allowDuplicateRelations()->noDuplicateRelations()->allowsDuplicateRelations())->toBeFalse();
})->with([
    'belongsToMany' => [fn () => Field::belongsToMany('Roles')],
    'morphToMany' => [fn () => Field::morphToMany('Tags')],
    'morphedByMany' => [fn () => Field::morphedByMany('Products')],
]);

it('marks which kinds create, attach and stay read-only', function () {
    expect(Field::hasMany('Items')->createsThroughPanel())->toBeTrue()
        ->and(Field::hasManyThrough('Deployments')->createsThroughPanel())->toBeFalse()
        ->and(Field::hasOneThrough('Owner')->createsThroughPanel())->toBeFalse()
        ->and(Field::belongsToMany('Roles')->attaches())->toBeTrue()
        ->and(Field::morphToMany('Tags')->attaches())->toBeTrue()
        ->and(Field::hasMany('Items')->attaches())->toBeFalse()
        ->and(Field::tag('Tags')->attaches())->toBeFalse()
        ->and(Field::hasOneOfMany('Latest Order')->createsThroughPanel())->toBeFalse();
});

it('freezes like any field', function () {
    Field::belongsTo('Customer')->freeze()->creatable();
})->throws(LogicException::class);

it('is a RelationField', function () {
    expect(Field::belongsTo('Customer'))->toBeInstanceOf(RelationField::class);
});
