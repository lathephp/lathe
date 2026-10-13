<?php

use Lathe\Field\AudioField;
use Lathe\Field\BadgeField;
use Lathe\Field\BooleanField;
use Lathe\Field\BooleanGroupField;
use Lathe\Field\CodeField;
use Lathe\Field\Contracts\FilterableField;
use Lathe\Field\CurrencyField;
use Lathe\Field\DateField;
use Lathe\Field\DateTimeField;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Field\FileField;
use Lathe\Field\GravatarField;
use Lathe\Field\HeadingField;
use Lathe\Field\IconField;
use Lathe\Field\IdField;
use Lathe\Field\ImageField;
use Lathe\Field\KeyValueField;
use Lathe\Field\LineField;
use Lathe\Field\MarkdownField;
use Lathe\Field\MultiSelectField;
use Lathe\Field\NumberField;
use Lathe\Field\PasswordField;
use Lathe\Field\RepeaterField;
use Lathe\Field\SelectField;
use Lathe\Field\SlugField;
use Lathe\Field\SparklineField;
use Lathe\Field\StackField;
use Lathe\Field\StatusField;
use Lathe\Field\TextareaField;
use Lathe\Field\TextField;
use Lathe\Field\TiptapField;
use Lathe\Field\TokenField;
use Lathe\Field\UiAvatarField;

const LATHE_ALL = ['index', 'detail', 'create', 'update'];
const LATHE_NOT_INDEX = ['detail', 'create', 'update'];
const LATHE_DISPLAY = ['index', 'detail'];

/**
 * @return list<string>
 */
function shownContexts(Field $field): array
{
    return array_values(array_map(fn (FieldContext $context) => $context->value, array_filter(FieldContext::cases(), fn (FieldContext $context) => $field->isShownOn($context))));
}

it('builds each non-relation factory with its class, component key and contexts', function (Closure $make, string $class, string $component, array $contexts) {
    $field = $make();

    expect($field::class)->toBe($class)
        ->and($field->getComponent())->toBe($component)
        ->and(shownContexts($field))->toBe($contexts);
})->with([
    'id' => [fn () => Field::id(), IdField::class, 'id-field', LATHE_DISPLAY],
    'text' => [fn () => Field::text('A'), TextField::class, 'text-field', LATHE_ALL],
    'textarea' => [fn () => Field::textarea('A'), TextareaField::class, 'textarea-field', LATHE_NOT_INDEX],
    'token' => [fn () => Field::token('A'), TokenField::class, 'token-field', LATHE_ALL],
    'number' => [fn () => Field::number('A'), NumberField::class, 'number-field', LATHE_ALL],
    'email' => [fn () => Field::email(), TextField::class, 'email-field', LATHE_ALL],
    'url' => [fn () => Field::url(), TextField::class, 'url-field', LATHE_ALL],
    'color' => [fn () => Field::color('A'), Field::class, 'color-field', LATHE_ALL],
    'password' => [fn () => Field::password(), PasswordField::class, 'password-field', LATHE_ALL],
    'passwordConfirmation' => [fn () => Field::passwordConfirmation('A'), PasswordField::class, 'password-field', ['create', 'update']],
    'boolean' => [fn () => Field::boolean('A'), BooleanField::class, 'boolean-field', LATHE_ALL],
    'select' => [fn () => Field::select('A'), SelectField::class, 'select-field', LATHE_ALL],
    'booleanGroup' => [fn () => Field::booleanGroup('A'), BooleanGroupField::class, 'boolean-group-field', LATHE_ALL],
    'multiSelect' => [fn () => Field::multiSelect('A'), MultiSelectField::class, 'multi-select-field', LATHE_ALL],
    'date' => [fn () => Field::date('A'), DateField::class, 'date-field', LATHE_ALL],
    'dateTime' => [fn () => Field::dateTime('A'), DateTimeField::class, 'date-time-field', LATHE_ALL],
    'currency' => [fn () => Field::currency('A'), CurrencyField::class, 'currency-field', LATHE_ALL],
    'badge' => [fn () => Field::badge('A'), BadgeField::class, 'badge-field', LATHE_DISPLAY],
    'icon' => [fn () => Field::icon('A'), IconField::class, 'icon-field', LATHE_DISPLAY],
    'status' => [fn () => Field::status('A'), StatusField::class, 'status-field', LATHE_DISPLAY],
    'sparkline' => [fn () => Field::sparkline('A'), SparklineField::class, 'sparkline-field', LATHE_DISPLAY],
    'stack' => [fn () => Field::stack('A'), StackField::class, 'stack-field', LATHE_DISPLAY],
    'line' => [fn () => Field::line('A'), LineField::class, 'line-field', LATHE_DISPLAY],
    'code' => [fn () => Field::code('A'), CodeField::class, 'code-field', LATHE_NOT_INDEX],
    'markdown' => [fn () => Field::markdown('A'), MarkdownField::class, 'markdown-field', LATHE_NOT_INDEX],
    'tiptap' => [fn () => Field::tiptap('A'), TiptapField::class, 'tiptap-field', LATHE_NOT_INDEX],
    'slug' => [fn () => Field::slug('A'), SlugField::class, 'slug-field', LATHE_ALL],
    'keyValue' => [fn () => Field::keyValue('A'), KeyValueField::class, 'key-value-field', LATHE_NOT_INDEX],
    'repeater' => [fn () => Field::repeater('A'), RepeaterField::class, 'repeater-field', LATHE_NOT_INDEX],
    'file' => [fn () => Field::file('A'), FileField::class, 'file-field', LATHE_NOT_INDEX],
    'image' => [fn () => Field::image('A'), ImageField::class, 'image-field', LATHE_ALL],
    'audio' => [fn () => Field::audio('A'), AudioField::class, 'audio-field', LATHE_NOT_INDEX],
    'avatar' => [fn () => Field::avatar(), ImageField::class, 'image-field', LATHE_ALL],
    'gravatar' => [fn () => Field::gravatar(), GravatarField::class, 'ui-avatar-field', LATHE_DISPLAY],
    'uiAvatar' => [fn () => Field::uiAvatar(), UiAvatarField::class, 'ui-avatar-field', LATHE_DISPLAY],
    'country' => [fn () => Field::country(), SelectField::class, 'select-field', LATHE_ALL],
    'timezone' => [fn () => Field::timezone(), SelectField::class, 'select-field', LATHE_ALL],
    'hidden' => [fn () => Field::hidden('A', 'a'), Field::class, 'hidden-field', ['create', 'update']],
    'heading' => [fn () => Field::heading('A'), HeadingField::class, 'heading-field', LATHE_NOT_INDEX],
]);

it('builds a computed, form-hidden field from a Closure attribute', function (Closure $make) {
    $field = $make();

    expect($field->isComputed())->toBeTrue()
        ->and($field->isShownOn(FieldContext::Create))->toBeFalse()
        ->and($field->isShownOn(FieldContext::Update))->toBeFalse();
})->with([
    'text' => [fn () => Field::text('A', fn () => 'x')],
    'number' => [fn () => Field::number('A', fn () => 1)],
    'email' => [fn () => Field::email('A', fn () => 'a@b.c')],
    'textarea' => [fn () => Field::textarea('A', fn () => 'x')],
    'boolean' => [fn () => Field::boolean('A', fn () => true)],
    'select' => [fn () => Field::select('A', fn () => 'x')],
    'badge' => [fn () => Field::badge('A', fn () => 'x')],
    'icon' => [fn () => Field::icon('A', fn () => 'x')],
    'status' => [fn () => Field::status('A', fn () => 'x')],
    'line' => [fn () => Field::line('A', fn () => 'x')],
]);

it('marks exactly the filterable non-relation types with FilterableField', function () {
    $factories = [
        'id' => Field::id(), 'text' => Field::text('A'), 'textarea' => Field::textarea('A'), 'token' => Field::token('A'), 'number' => Field::number('A'),
        'email' => Field::email(), 'url' => Field::url(), 'color' => Field::color('A'), 'password' => Field::password(), 'boolean' => Field::boolean('A'),
        'select' => Field::select('A'), 'booleanGroup' => Field::booleanGroup('A'), 'multiSelect' => Field::multiSelect('A'), 'date' => Field::date('A'),
        'dateTime' => Field::dateTime('A'), 'currency' => Field::currency('A'), 'badge' => Field::badge('A'), 'icon' => Field::icon('A'), 'status' => Field::status('A'),
        'sparkline' => Field::sparkline('A'), 'stack' => Field::stack('A'), 'line' => Field::line('A'), 'code' => Field::code('A'), 'markdown' => Field::markdown('A'),
        'tiptap' => Field::tiptap('A'), 'slug' => Field::slug('A'), 'keyValue' => Field::keyValue('A'), 'repeater' => Field::repeater('A'), 'file' => Field::file('A'),
        'image' => Field::image('A'), 'audio' => Field::audio('A'), 'hidden' => Field::hidden('A', 'a'), 'heading' => Field::heading('A'),
    ];
    $classes = array_values(array_unique(array_map(
        fn (Field $field) => class_basename($field),
        array_filter($factories, fn (Field $field) => $field instanceof FilterableField),
    )));
    sort($classes);

    expect($classes)->toBe(['BadgeField', 'BooleanField', 'BooleanGroupField', 'DateField', 'DateTimeField', 'MarkdownField', 'MultiSelectField', 'NumberField', 'SelectField', 'SlugField', 'StatusField', 'TextField', 'TextareaField', 'TiptapField', 'TokenField']);
});
