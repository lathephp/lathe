---
title: Quickstart
description: Build a list, forms and a detail page for a blog's posts in about five minutes.
order: 2
---

# Quickstart

This guide builds an admin for a blog: a `Post` that belongs to a `User`. At the end you have a list with search and sorting, create and edit forms, a detail page and a link to the author, without writing any frontend code.

It assumes Lathe is installed and you can sign in at `/lathe`. If not, follow [Installation](installation.md) first.

## Step 1: the model and its table

Lathe works on your Eloquent models, so start with plain Laravel:

```bash
php artisan make:model Post -m
```

In the new migration, add the columns:

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('title');
    $table->string('status')->default('draft');
    $table->text('body')->nullable();
    $table->timestamp('published_at')->nullable();
    $table->timestamps();
});
```

Give the model its relation and casts:

```php
class Post extends Model
{
    protected $fillable = ['user_id', 'title', 'status', 'body', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

Then run `php artisan migrate`.

## Step 2: the Resource

A Resource tells Lathe how to show a model. Generate one:

```bash
php artisan lathe:resource Post
```

It writes `app/Lathe/Resources/Post.php` and a policy, `app/Policies/PostPolicy.php`. Lathe finds every Resource in `app/Lathe/Resources`, so Posts is already in the sidebar.

## Step 3: the fields

Open the Resource and describe the post:

```php
namespace App\Lathe\Resources;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;

class Post extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(\App\Models\Post::class)
            ->title('title')
            ->searchable(['title']);
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::id()->sortable(),
            Field::text('Title')->required()->rules('max:255')->sortable(),
            Field::belongsTo('Author', 'user', User::class),
            Field::select('Status')->options(['draft', 'published'])->default('draft')->required(),
            Field::markdown('Body')->nullable(),
            Field::dateTime('Published at')->nullable()->sortable(),
        ];
    }
}
```

`title('title')` names the column that stands for a post in links and pickers. `searchable()` turns on the search box. The [Fields](../your-first-resource/fields.md) page lists every field type.

## Step 4: who may do what

Lathe denies every action the policy has no method for. The generated policy allows viewing only, so add the rest:

```php
public function create(Authenticatable $user): bool
{
    return true;
}

public function update(Authenticatable $user, Post $post): bool
{
    return true;
}

public function delete(Authenticatable $user, Post $post): bool
{
    return true;
}

// The Author field links a post to a user, which needs its own ability: add, then the relation name.
public function addUser(Authenticatable $user, Post $post): bool
{
    return true;
}
```

Without `addUser()`, saving the form fails with "You are not authorized to relate this resource." Return your own rules here, such as `$post->user_id === $user->id`. See [Policies](../security/policies.md).

## Step 5: open the panel

Open `/lathe/resources/posts`. Press "Create post", fill the form and choose the author (a picker that searches as you type once there are many users), then save. You land on the detail page; the list shows the post, sorted and searchable.

## Next steps

- Add filters and lenses: [Filters](../querying/filters.md), [Lenses](../querying/lenses.md).
- Act on many posts at once: [Actions](../taking-action/actions.md).
- Put numbers on a dashboard: [Dashboards](../organizing/dashboards.md).

## What you see

The sidebar lists Posts. The list shows each post's id, title, author, status and date, with a search box and sortable columns, and "Create post" opens a form with the author picker.
