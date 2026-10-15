---
title: Validation
description: Attach Laravel validation rules to fields, change messages, and add checks after the rules pass.
order: 5
---

# Validation

Lathe validates every create and update with Laravel's validator. You attach rules to fields. Lathe collects them and runs them before it saves the record.

Lathe checks only the keys that a field owns. Input with any other key is never validated and never saved. A failed check returns status 422 with the errors keyed by field, and the form shows each message under its field.

## Attach rules

Pass rules to `rules()`. They apply to creates and updates:

```php
Field::text('Name')->rules('required', 'max:255'),
```

A rule can be a string, a pipe-separated string, an array, or a rule object:

```php
use App\Rules\ValidState;

Field::text('State')->rules('required', new ValidState),

Field::text('State')->rules(['required', new ValidState]),
```

A closure works as a Laravel closure rule. It receives the attribute name, the value and a `$fail` callback:

```php
Field::text('Username')
    ->rules('required', function (string $attribute, mixed $value, Closure $fail) {
        if (strtolower($value) === 'admin') {
            $fail('The username "admin" is reserved.');
        }
    }),
```

A field marked `required()` gets the `required` rule first, so you do not repeat it. A field marked `nullable()` gets `nullable`.

Lathe adds rules that the field type implies, unless you state them yourself. A number field gets `numeric` and its `min` and `max`. A date field gets `date`. A select field gets `in` over its option values. A file field gets `file`, a size limit and the allowed types.

Fields that are read-only, computed or hidden by a `visibleWhen()` condition are skipped. See [Fields](fields.md).

### Rules for creating and updating

`creationRules()` adds rules on create only, and `updateRules()` adds rules on update only. They come after the rules from `rules()`:

```php
Field::password('Password')
    ->rules('min:8')
    ->creationRules('required')
    ->updateRules('nullable'),
```

On update, the placeholder `{{resourceId}}` in a rule string becomes the record's key. Use it so a unique rule ignores the record being edited. On create it becomes `NULL`, so the same rule in `rules()` ignores nothing:

```php
Field::text('Email')
    ->rules('required', 'email', 'max:255')
    ->creationRules('unique:users,email')
    ->updateRules('unique:users,email,{{resourceId}}'),
```

## Messages

`messages()` replaces the message for a rule on one field. The array keys are rule names:

```php
Field::text('Name')
    ->rules('required', 'max:255')
    ->messages([
        'required' => 'Enter a name for this record.',
        'max' => 'The name must be 255 characters or fewer.',
    ]),
```

## Attribute names

Messages call a field by its label. `validationAttribute()` sets another name:

```php
Field::text('Full name', 'name')
    ->rules('required')
    ->validationAttribute('name'),
```

The `required` message then reads "The name field is required." instead of "The Full name field is required." A `hasOne` field names its nested keys from the related Resource's field labels, so `address.city` reads "City" without any setup.

## Checks after the rules

Sometimes a check needs more than one field. Override these static methods on the Resource. Each receives the `LatheRequest` and the validator, and runs after all field rules ran:

- `afterValidation()` runs on every create and update, first.
- `afterCreationValidation()` runs next, on create only.
- `afterUpdateValidation()` runs next, on update only.

Add an error to the validator to refuse the save:

```php
use Illuminate\Contracts\Validation\Validator;
use Lathe\Http\Requests\LatheRequest;

public static function afterValidation(LatheRequest $request, Validator $validator): void
{
    if ($request->input('start_date') > $request->input('end_date')) {
        $validator->errors()->add('start_date', 'The start date must be before the end date.');
    }
}
```

## Relations

Relation fields validate themselves. You do not configure these rules:

- `Relatable` checks that the chosen record is one the picker offers and that the user may add it. It fails with "The :attribute must be a valid relation." or "You are not authorized to relate this resource."
- `NotAttached` stops a `belongsToMany` or `morphToMany` field from attaching the same record twice. With `allowDuplicateRelations()`, `NotExactlyAttached` stops only an identical row. See [Relationships](relationships.md).
- A `morphTo` field also checks that the submitted type is one of its `types()`.
- A `hasOne` field validates the related Resource's fields under the relation name. The `city` field of the `address` relation uses the key `address.city`.

Pivot fields of a `belongsToMany` or `morphToMany` field validate under `pivot.` plus their attribute. This pivot field is checked under `pivot.assigned_at`:

```php
Field::belongsToMany('Roles', 'roles', Role::class)
    ->pivotFields([
        Field::dateTime('Assigned at', 'assigned_at')->rules('required'),
    ]),
```

## What you see

Submit a create or edit form with a bad value. The form stays open, keeps what you typed, and shows a red message under each field that failed. The message uses your custom text and the name you set for the field. A check you added with `afterValidation()` shows its message under the field you named.
