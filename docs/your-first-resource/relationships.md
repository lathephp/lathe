---
title: Relationships
description: Show and edit Eloquent relationships with relation fields, pivot data, pickers and panels.
order: 4
---

# Relationships

Lathe has a field for each Eloquent relationship. A relation field shows the related records and, where it makes sense, lets the user choose or create them. Add the fields to `fields()` like any other field.

Every relation field is a static method on `Field`. The three arguments are the label, the name of the relationship method on your model, and the related Resource class. The last two are optional:

```php
use Lathe\Field\Field;

Field::hasMany('Comments', 'comments', Comment::class),
```

The second argument is the name of the Eloquent relationship method, never the foreign key column. The class is a Resource class, such as `App\Lathe\Resources\Comment`, not a model. The examples on this page import the Resources they name.

## Where each field appears

| Field | Index | Detail | Forms |
|---|---|---|---|
| `belongsTo`, `morphTo`, `tag` | Yes | Yes | Yes, as a picker |
| `hasOne`, `morphOne` | No | A panel | Yes, as an inline block |
| `hasOneOfMany` | No | Yes, read-only | No |
| `hasMany`, `morphMany` | No | A panel | No |
| `belongsToMany`, `morphToMany`, `morphedByMany` | No | A panel | No |
| `hasOneThrough`, `hasManyThrough` | No | A read-only panel | No |

The through fields never create, attach or detach records.

> Note: Policies decide who may add, attach and detach related records. A policy that lacks the matching method denies the action. See [Policies](../security/policies.md).

## Guess the related Resource

When you leave out the second and third arguments, Lathe guesses them. It turns the label into a relationship name (`User Profile` becomes `userProfile`). It then finds the Resource for the related model. If no Resource is registered for it, Lathe tries `App\Lathe\Resources\` followed by the singular class name.

Give the arguments, or call `resource()`, when the guess is wrong:

```php
Field::belongsTo('Author', 'user', User::class),

Field::belongsTo('Author', 'user')->resource(User::class),
```

## HasOne

`hasOne` matches an Eloquent `hasOne` relationship. A user has one address:

```php
Field::hasOne('Address', 'address', Address::class),
```

On the create and edit forms, the field shows the related Resource's fields inline, under the relation name. The user fills both records in one step. On save, Lathe stores the parent first. It then creates the related row if there is none and updates it if there is, in the same database transaction.

The input keys use the relation name as a prefix, so the `city` field of the `address` relation validates under `address.city`. The error message uses the label of the related field.

A `hasOne` field is nullable. The nested rules apply only when the form sends data for the relation.

### Has one of many

`hasOneOfMany` shows one row picked by an Eloquent `latestOfMany()`, `oldestOfMany()` or `ofMany()` relationship. It is read-only and appears only on the detail page:

```php
Field::hasOneOfMany('Latest order', 'latestOrder', Order::class),
```

### When the record is already filled

The detail panel hides its Create button once the related row exists. To decide that another way, pass a closure to `alreadyFilledWhen()`. It receives the request and the parent model:

```php
Field::hasOne('Warranty', 'warranty', Warranty::class)
    ->alreadyFilledWhen(fn ($request, $user) => $user->warranty_expires_at !== null),
```

A create through the panel is refused with a 422 while the closure returns `true`.

## HasMany

`hasMany` shows a sub-table on the detail page. A post has many comments:

```php
Field::hasMany('Comments', 'comments', Comment::class),
```

The sub-table has its own search, filters, sort order, paging and row actions. It loads when the user opens it. A Create button opens the related create form with the foreign key locked to the parent.

## HasOneThrough and HasManyThrough

These show a read-only panel for `hasOneThrough` and `hasManyThrough` relationships:

```php
Field::hasOneThrough('Account history', 'accountHistory', AccountHistory::class),

Field::hasManyThrough('Posts', 'posts', Post::class),
```

## BelongsTo

`belongsTo` matches an Eloquent `belongsTo` relationship. It shows a picker on forms and a link on the index and detail pages. A post belongs to an author:

```php
Field::belongsTo('Author', 'author', User::class),
```

The field is required unless you call `nullable()`:

```php
Field::belongsTo('Category', 'category', Category::class)->nullable(),
```

### Run code after the key is set

`filled()` takes a closure that runs after Lathe writes the foreign key. It receives the request and the model:

```php
Field::belongsTo('Author', 'author', User::class)
    ->filled(fn ($request, $post) => $post->syncAuthorMeta()),
```

### Hide the parent in a panel

When a user opens a record from a `hasMany` panel, the child's own `belongsTo` field that points back to the parent adds nothing. Lathe leaves it out of that view when the related Resource's model is the parent's class. If the guess is not enough, name the parent's relationship with `inverse()`:

```php
Field::belongsTo('Post', 'post', Post::class)->inverse('comments'),
```

The field stays visible everywhere else. `morphTo` fields have `inverse()` too.

### Peek

Hovering a related value on the index or detail page shows a peek card of the related record, when the related Resource defines peek fields (see `fieldsForPeeking()` in [Defining resources](defining-resources.md)). Turn it off with `noPeeking()`, or decide per request with `peekable()`:

```php
Field::belongsTo('Author', 'author', User::class)->noPeeking(),

Field::belongsTo('Author', 'author', User::class)
    ->peekable(fn ($request) => $request->user()->is_admin),
```

### Display text and links

The picker and the detail page show the related Resource's title. Replace it with `display()`, which receives the related model. `withSubtitles()` adds the subtitle under each choice in the picker:

```php
Field::belongsTo('Author', 'author', User::class)
    ->display(fn ($user) => $user->name.' ('.$user->email.')')
    ->withSubtitles(),
```

A related value links to its detail page when the user may view it. `viewable(false)` removes the link.

> Note: If `display()` reads a column that is not the title, subtitle or cover, add it to the related Resource's `optionColumns()`. The picker selects only the columns that method names.

### Soft-deleted records

If the related model uses soft deletes, `withTrashed()` adds a "With Trashed" checkbox to the picker. `withoutTrashed()` hides the checkbox and leaves trashed records out:

```php
Field::belongsTo('Author', 'author', User::class)->withTrashed(),
```

## BelongsToMany

`belongsToMany` matches an Eloquent `belongsToMany` relationship. A user has many roles:

```php
Field::belongsToMany('Roles', 'roles', Role::class),
```

The detail page shows a panel with an Attach button and a Detach button on each row.

### Pivot fields

Fields that live on the pivot table go in `pivotFields()`. Pass a list of fields, or a closure that receives the request and, when there is one, the related model:

```php
Field::belongsToMany('Roles', 'roles', Role::class)
    ->pivotFields(fn ($request, ?Model $related) => [
        Field::dateTime('Assigned at', 'assigned_at'),
        Field::text('Notes'),
    ]),
```

The related model is `null` when the closure runs for no particular row, such as during validation. Declare the columns on the Eloquent relationship too:

```php
public function roles(): BelongsToMany
{
    return $this->belongsToMany(Role::class)->withPivot('assigned_at', 'notes');
}
```

The attach modal shows these fields next to the picker. A row's pivot form edits them later. Lathe validates them under the key `pivot.assigned_at`.

### Pivot actions

`pivotActions()` adds actions that run on selected pivot rows:

```php
Field::belongsToMany('Roles', 'roles', Role::class)
    ->pivotActions(fn () => [new RenewRole]),
```

### Duplicate attachments

A record can be attached once by default. To allow the same record twice, call `allowDuplicateRelations()`. The pivot table then needs its own primary key. Call `noDuplicateRelations()` to go back to the default:

```php
Field::belongsToMany('Books', 'books', Book::class)->allowDuplicateRelations(),
```

## Polymorphic relationships

`morphOne`, `morphMany`, `morphToMany` and `morphedByMany` work like their non-polymorphic partners. Only the field name changes:

```php
Field::morphOne('Featured image', 'image', Image::class),

Field::morphMany('Comments', 'comments', Comment::class),

Field::morphToMany('Tags', 'tags', Tag::class),

Field::morphedByMany('Posts', 'posts', Post::class),
```

`morphToMany` and `morphedByMany` accept `pivotFields()`, `pivotActions()` and the duplicate settings, as `belongsToMany` does.

## MorphTo

`morphTo` shows a type select and then a record picker. Name the possible Resources with `types()`:

```php
Field::morphTo('Commentable', 'commentable')
    ->types([Post::class, Video::class]),
```

Lathe rejects any type that is not in the list. The form sends the Resource's URI key, never a class name, so a user cannot make Lathe load an arbitrary model.

Useful options:

```php
Field::morphTo('Commentable', 'commentable')
    ->types([Post::class, Video::class])
    ->nullable()
    ->defaultResource(Post::class)
    ->noPeeking()
    ->displaysWithTrashed(false),
```

`defaultResource()` picks the type that is selected on the create form. `displaysWithTrashed(false)` hides the "With Trashed" checkbox. `display()` works as it does on `belongsTo`.

## Tag

`tag` is a compact `belongsToMany` for labels. It shows the related records as tags on the index and detail pages and syncs their ids, with no pivot data:

```php
Field::tag('Tags', 'tags', Tag::class),
```

Tags appear in a row by default. These options change how they look and load:

```php
Field::tag('Tags', 'tags', Tag::class)
    ->displayAsList()
    ->withPreview()
    ->preload(),
```

- `displayAsList()` stacks the tags. `displayAsGroup()` puts them in a row, which is the default.
- `withPreview()` opens the related record in the [preview panel](../querying/preview-panel.md) over the page when the user clicks a tag.
- `preload()` loads every tag when the form opens. It applies only when the related table has at most 100 rows. Larger tables use the search picker.

A `tag` field has no pivot fields. Use `belongsToMany` when you need them.

## Panel settings

These apply to relation fields that show a panel:

```php
Field::hasMany('Comments', 'comments', Comment::class)
    ->collapsible()
    ->collapsed()
    ->singularLabel('Reply')
    ->pluralLabel('Replies')
    ->perPageOptions([10, 25, 50])
    ->withCount(),
```

- `collapsible()` lets the user fold the panel. `collapsed()` starts it folded.
- `singularLabel()` and `pluralLabel()` replace the labels taken from the related Resource.
- `perPageOptions()` sets the page size choices of the panel. The first is the default and each is 1 to 100.
- `withCount()` adds a column with the number of related records to the index table. It counts all rows of a page in one query.

## Pickers

### Search

A picker loads its first page when the user opens it. For a table with many rows, call `searchable()`, and the user types to search instead. If the first page has more rows than fit, Lathe switches to the search picker by itself. `debounce()` sets the typing delay in milliseconds.

```php
Field::belongsTo('User', 'user', User::class)
    ->searchable()
    ->withSubtitles()
    ->debounce(300),
```

The page size comes from the related Resource's `pickerResults()` setting, 25 by default. A request cannot change it.

### Create from the form

`creatable()` adds a button that opens the related create form in a modal. The new record is selected when the user saves it. `modalSize()` takes `sm`, `md`, `lg`, `xl`, `2xl`, `3xl`, `4xl`, `5xl`, `6xl` or `7xl`:

```php
Field::belongsTo('Author', 'author', User::class)
    ->creatable()
    ->modalSize('xl'),
```

The user needs the `create` ability on the related Resource and the `add` ability on the parent. A modal cannot open another creation modal.

### Limit the choices

Three hooks narrow the records a picker offers. A field's `relatableQuery()` runs first and receives the request and a query builder:

```php
use Illuminate\Database\Eloquent\Builder;

Field::belongsTo('Author', 'author', User::class)
    ->relatableQuery(fn ($request, Builder $query) => $query->where('active', true)),
```

Next, the parent Resource can define a static method named `relatable` plus the plural of the related model's class name, such as `relatableTags()`. It receives the request, the builder and the field:

```php
public static function relatableTags(LatheRequest $request, Builder $query, Field $field): Builder
{
    return $query->where('tenant_id', $request->user()->tenant_id);
}
```

When the parent defines that method, it replaces the related Resource's own `relatableQuery()` hook. Without it, the related Resource's hook runs. See [Defining resources](defining-resources.md).

By default, Lathe sorts the choices by title after the hooks run. Call `reorderAssociatables(false)` to keep the order your query sets:

```php
Field::belongsTo('Author', 'author', User::class)
    ->relatableQuery(fn ($request, Builder $query) => $query->orderBy('priority'))
    ->reorderAssociatables(false),
```

### Depend on another field

`dependsOn()` asks the server for changes when another field changes. `dependsOnCreating()` and `dependsOnUpdating()` do the same on one form only. See [Fields](fields.md) for the callback and what it can change.

## What you see

On a record's detail page, each `hasMany`, `belongsToMany` and morph field is a panel with its own search, sort and paging, and an Attach or Create button where the field allows it. On the create and edit forms, `belongsTo` and `morphTo` are pickers that load when you open them, `hasOne` is a block of nested fields, and `tag` is a tag input. The index table shows related titles as links with a peek card on hover.
