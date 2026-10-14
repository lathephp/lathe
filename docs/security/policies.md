---
title: Policies and gates
description: Control who opens the panel and what each user may do in a Resource, with the viewLathe gate and Laravel policies that deny by default.
order: 2
---

# Policies and gates

Lathe asks two questions on every request. First, may this user open the panel at all? A gate named `viewLathe` answers that. Second, may this user do this thing to this record? The Resource's policy answers that. Both use Laravel's own authorization, so a policy you already have keeps working.

## The viewLathe gate

Every panel request needs a user who is signed in on the panel guard and passes the `viewLathe` gate. This holds in every environment, `local` included. The gate in your `app/Providers/LatheServiceProvider.php` starts with an empty list of emails, so nobody gets in until you add one:

```php
use Illuminate\Support\Facades\Gate;

protected function gate(): void
{
    Gate::define('viewLathe', fn (object $user): bool => in_array(data_get($user, 'email'), [
        'admin@example.com',
    ], true));
}
```

For a rule that is not a list of emails, return your own check from the closure, such as a role or a column on the user.

A signed-out request gets a 401 JSON answer from the API and a redirect to the login page otherwise. A signed-in user who fails the gate gets a 403 page inside the panel shell with a button to sign out.

### Replace the gate

`Lathe::auth()` takes a callback in place of the gate. Call it from the `authorization()` method of your `LatheServiceProvider`:

```php
use Illuminate\Http\Request;
use Lathe\Lathe;

protected function authorization(): void
{
    Lathe::auth(fn (Request $request) => $request->user()?->isStaff() === true);
}
```

The callback must return `true` to admit the user. Any other value, including a truthy one, denies. A signed-out request never reaches the callback. `Lathe::check($request)` runs the same check and returns a boolean.

## Deny by default

Lathe uses the policy that Laravel finds for the Resource's model. It denies in three cases:

- A Resource has no policy. Every ability is denied, and the 403 message names the Resource and says how to fix it.
- A policy has no method for the ability. That ability is denied. This includes `viewAny`, so a policy without it hides the Resource from the sidebar.
- There is no signed-in user.

`php artisan lathe:resource Post` writes a policy next to the Resource when the model has none. The generated policy allows `viewAny` and `view` and nothing else. Add a method for each thing you want to allow.

A policy method returns a boolean. This one lets every user read posts and lets only the author edit:

```php
namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Post $post): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Post $post): bool
    {
        return $post->user_id === $user->id;
    }
}
```

Laravel's `before()` hook on a policy still runs, so a super-admin rule there applies to the panel too.

### Skip the policy

A Resource may opt out of policy checks with `allowAll()` in `configure()`:

```php
protected function configure(ResourceDefinition $definition): ResourceDefinition
{
    return $definition->model(\App\Models\Category::class)->allowAll();
}
```

Every ability is then allowed for every user who can open the panel. Lathe logs a warning at boot that names the Resource. Use it for a demo or a table with no secrets, not for real data.

### Use a policy that is not the model's

Set the static `$policy` property on the Resource to the class name when the Gate would not find the right one:

```php
class Post extends Resource
{
    public static ?string $policy = \App\Policies\PanelPostPolicy::class;
}
```

Lathe then calls that class directly and skips the Gate for this Resource.

### Deny an ability always

`deniedAbilities()` on a Resource lists abilities it never grants, whatever the policy says. A read-only log can deny every write:

```php
public static function deniedAbilities(): array
{
    return ['create', 'update', 'delete', 'forceDelete', 'restore', 'replicate'];
}
```

### Hide a Resource

`canSee()` on the definition hides the whole Resource from requests the closure refuses. The sidebar entry disappears and every endpoint of the Resource answers 403:

```php
$definition->canSee(fn (Request $request) => $request->user()?->isAdmin() === true);
```

## What Lathe asks for

Policy methods take the user first. Lathe asks for these:

| Ability | Method | Used for |
|---|---|---|
| `viewAny` | `viewAny(User)` | The index, the sidebar entry, relation tables and pickers. |
| `view` | `view(User, Model)` | The detail page, peek and file downloads. |
| `create` | `create(User)` | The create form and creating a record. |
| `update` | `update(User, Model)` | The edit form, saving, and uploads on a field. |
| `inlineEdit` | `inlineEdit(User, Model)` | Editing a cell in the index. It also needs `update`. |
| `delete` | `delete(User, Model)` | Deleting one record. |
| `restore` | `restore(User, Model)` | Restoring a soft-deleted record. |
| `forceDelete` | `forceDelete(User, Model)` | Deleting a record for good. |
| `replicate` | `replicate(User, Model)` | Copying a record. Without this method, Lathe allows it when both `create` and `update` pass. |
| `export` | `export(User)` | The export action. |
| `import` | `import(User)` | The import action. |
| `runAction` | `runAction(User, Model, Action)` | Running an action. |
| `runDestructiveAction` | `runDestructiveAction(User, Model, Action)` | Also asked for an action marked destructive. |
| `viewLens` | `viewLens(User, string $lensKey)` | Opening a lens. |
| `viewWidget` | `viewWidget(User, string $widgetKey)` | Showing a widget on a Resource. |

Two more are not policy methods. `globalSearch` is a gate: define it with `Gate::define('globalSearch', ...)` to allow the header search. `viewLathe` is the gate described above.

A policy that has no `viewLens` or `viewWidget` method hides every lens and widget of the Resource.

### Many records at once

Bulk delete, restore and force delete look for a method named after the ability with `Any` on the end: `deleteAny`, `restoreAny` and `forceDeleteAny`. It takes the user and the query, and returns the query narrowed to the records the user may touch:

```php
use Illuminate\Database\Eloquent\Builder;

public function deleteAny(User $user, Builder $query): Builder
{
    return $query->where('user_id', $user->id);
}
```

It may also return `true` for every record or `false` for none. Without the method, Lathe asks the single-record ability for each record and skips the ones that fail.

### Rows on a page

For each row on a page, Lathe asks the policy about `view`, `update`, `delete` and `replicate`, plus `restore` and `forceDelete` on a soft-delete Resource. If your checks run queries, add `abilitiesFor(User $user, Collection $models): array` to the policy. It answers for the whole page in one call and returns `[key => ['view' => true, 'update' => false, ...]]`. Without it, Lathe calls the policy for each row and ability.

## Relationships

A relation field asks the policy of the parent model. The method name ends with the relation name in studly case. For a `comments` relation on a post:

| Method | Asked when |
|---|---|
| `addComments(User, Post)` | The user adds a new related record from the parent. |
| `attachComments(User, Post, array $ids)` | The user attaches the records with those ids. |
| `attachAnyComments(User, Post)` | The attach picker opens at all. |
| `detachComments(User, Post, array $ids)` | The user detaches the records with those ids. |

Lathe asks `attach` and `detach` with the real ids at submit time. The attach methods can also return an array of the ids they allow. Lathe then keeps those ids and drops the others, and the picker leaves out the ones that failed.

```php
public function attachComments(User $user, Post $post, array $ids): bool
{
    return $user->can('moderate', $post);
}

public function addComments(User $user, Post $post): bool
{
    return $user->isAdmin();
}
```

Adding a related record also needs `create` on the related Resource.

## Impersonation

Impersonation does not use a policy method. The user model decides it. See [Impersonation](impersonation.md).

## Test your policies

Test a policy with a plain unit test. Test the whole panel path with the Resource tester. See [Testing](../your-first-resource/testing.md).

## What you see

A user who passes `viewLathe` sees only the Resources whose policy lets them `viewAny`. On each page, buttons for create, edit, delete, restore and the actions appear only when the policy allows them, and the same request sent by hand answers 403. A Resource with no policy shows no sidebar entry and answers 403 with a message that tells you to write a policy or call `allowAll()`.
