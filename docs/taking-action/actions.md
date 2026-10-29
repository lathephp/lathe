---
title: Actions
description: Write actions that run on selected rows, one row or no rows, and control their form, results, queueing and permissions.
order: 1
---

# Actions

An action is a PHP class that does something to records: send an email, archive a post, assign a role. The user ticks rows, picks the action from a menu, fills in an optional form, and Lathe runs it.

An action can also run from one row's menu (an inline action), from the detail page, or with no rows at all.

## Create an action

```bash
php artisan lathe:action SendWelcomeEmail
```

This writes `app/Lathe/Actions/SendWelcomeEmail.php`. Add `--force` to overwrite a file, or `--plugin=vendor/name` to write into a plugin package. See [Commands](../getting-started/commands.md).

The `handle()` method does the work. It receives the values of the action's form and a collection of the selected models, and returns an `ActionResult`:

```php
namespace App\Lathe\Actions;

use Illuminate\Support\Collection;
use Lathe\Actions\Action;
use Lathe\Actions\ActionFields;
use Lathe\Actions\ActionResult;

class SendWelcomeEmail extends Action
{
    public function handle(ActionFields $fields, Collection $models): ActionResult
    {
        foreach ($models as $user) {
            $user->sendWelcomeEmail();
        }

        return ActionResult::message('Welcome emails sent.');
    }
}
```

A plain `handle()` that returns nothing useful gets the default message "Action was executed successfully."

## Register an action

Return the action from the Resource's `actions()` method:

```php
use App\Lathe\Actions\SendWelcomeEmail;
use Lathe\Http\Requests\LatheRequest;

public function actions(LatheRequest $request): array
{
    return [
        new SendWelcomeEmail,
    ];
}
```

A [lens](../querying/lenses.md) has no `actions()` method. Its rows run the Resource's actions.

The same action then appears in the toolbar menu of the index, on the detail page and, when you ask for it, in each row's menu.

## Name and key

The name comes from the class: `SendWelcomeEmail` shows as "Send Welcome Email". The URI key is the kebab form, `send-welcome-email`, and appears in routes and in the audit log. Change either:

```php
(new SendWelcomeEmail)->withName('Send onboarding email')->withUriKey('onboarding-email')
```

Two actions on one Resource may not share a key.

## One handler per model type

An action registered on a Resource that returns several model types can define a handler per type. Lathe picks the method from the model's class name: `handleForOrders`, then `handleForOrder`, then `handle`.

```php
public function handleForUser(ActionFields $fields, Collection $models): ActionResult
{
    // Runs for App\Models\User.
}

public function handleForPost(ActionFields $fields, Collection $models): ActionResult
{
    // Runs for App\Models\Post.
}
```

## A form for the action

Return fields from `fields()` to ask the user for input before the action runs. They are the same field classes as a Resource's form. See [Fields](../your-first-resource/fields.md).

```php
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;

public function fields(LatheRequest $request): array
{
    return [
        Field::text('Subject', 'subject')->rules('required', 'min:3'),
        Field::textarea('Message', 'message')->required(),
    ];
}
```

`handle()` reads the values from `ActionFields`. Use property access, array access or `get()`:

```php
Mail::to($user)->send(new CustomEmail($fields->subject, $fields->message));
```

Lathe checks the fields' rules before `handle()` runs. A failure answers 422 under the field, and the modal stays open with its values. Use `default()` on a field for a starting value.

For a check across several fields, override `afterValidation()`:

```php
use Illuminate\Contracts\Validation\Validator;

public function afterValidation(LatheRequest $request, Validator $validator): void
{
    if ($request->input('fields.subject') === 'test') {
        $validator->errors()->add('subject', 'Pick a real subject.');
    }
}
```

## Results

`handle()` returns an `ActionResult`. Each factory sets a message, one effect, or both.

| Factory | What the user sees |
|---|---|
| `ActionResult::message($text)` | A success toast. |
| `ActionResult::danger($text)` | An error toast. |
| `ActionResult::redirect($url, $newTab = false)` | The browser goes to the URL. |
| `ActionResult::openInNewTab($url)` | The URL opens in a new tab. |
| `ActionResult::visit($path, $options = [])` | A client-side visit to a panel page. |
| `ActionResult::download($name, $url)` | The browser saves the file. |
| `ActionResult::modal($component, $data = [])` | A modal you registered with `actionModal()` in `@lathephp/core`. |
| `ActionResult::emit($event, $data = [])` | A `lathe:<event>` event on the window, for plugins. |
| `ActionResult::deleted()` | The list refreshes and a toast says the record was deleted. |

A URL must be relative, `http` or `https`. Any other scheme throws.

Chain a second part onto a result. The chain methods are `withMessage()`, `withDangerMessage()`, `withRedirect()`, `usingNewTab()`, `withVisitOptions()`, `withDownload()`, `withModal()`, `withEvent()` and `withDeleted()`. A result holds at most one effect, so a second effect replaces the first.

```php
return ActionResult::message('User archived.')
    ->withRedirect('/lathe/resources/users');
```

## Where an action shows

An action shows on the index and the detail page, and not on a table row. Narrow or widen that with these methods:

```php
(new SendWelcomeEmail)->onlyOnIndex()
(new SendWelcomeEmail)->onlyOnDetail()
(new SendWelcomeEmail)->exceptOnIndex()
(new SendWelcomeEmail)->exceptOnDetail()
(new Approve)->showInline()       // also on each row
(new Approve)->onlyInline()       // only on each row
(new Approve)->exceptInline()     // not on each row
```

`onlyOnTableRow()` and `exceptOnTableRow()` are aliases of `onlyInline()` and `exceptInline()`.

## Standalone and sole actions

A standalone action needs no selection. It runs once with an empty collection, which suits reports, imports and calls to other services. Call `standalone()` or use the `#[Standalone]` attribute.

A sole action needs exactly one row. A request with more rows answers 422. Call `sole()` or use `#[Sole]`.

An action that is neither needs at least one selected row, or Lathe answers 422 "Select at least one row."

## Destructive actions and confirmation

Extend `Lathe\Actions\DestructiveAction` for an action that destroys data. It always asks for confirmation, shows a red button, and needs the extra `runDestructiveAction` permission (see [Permissions](#permissions)).

```php
use Lathe\Actions\DestructiveAction;

class DeleteExpiredPosts extends DestructiveAction
{
    public function handle(ActionFields $fields, Collection $models): ActionResult
    {
        $models->each->delete();

        return ActionResult::deleted();
    }
}
```

A regular action asks for confirmation only when you say so:

```php
(new SendBulkEmail)->confirmable('Send this email to :count users?')
(new SendBulkEmail)->confirmable(fn (int $count) => "Send to {$count} users?")
```

`:count` in a string becomes the number of selected rows, and `:title` the record's title when the action runs on one record the user may view; otherwise `:title` drops out, so "Cancel order :title?" reads "Cancel order?". A closure receives the count, so it can word one record and several apart:

```php
(new CancelOrder)->confirmText(fn (int $count) => $count === 1 ? 'Cancel order :title?' : 'Cancel :count orders?')
```

A destructive confirm names its safe button for what it keeps, such as `cancelButtonText('Keep order')`; that button holds the focus when the dialog opens.

| Method | Effect |
|---|---|
| `confirmText($text)` | Sets the text without turning confirmation on. |
| `confirmButtonText($text)` | Names the confirm button. The default is the action's name, so the button repeats its verb. |
| `cancelButtonText($text)` | Names the cancel button. The default is "Cancel". |
| `withoutConfirmation()` | Turns confirmation off, even on a destructive action. |

An action with a form always opens its modal, whether or not it is confirmable.

`requirePassword()` asks for the user's password before a run, unless they confirmed it within `auth.password_timeout` (three hours by default). The run answers 423 until then, the open dialog shows "Confirm your password" in place of its content, and after a correct password the run goes on. The check comes before anything runs, including an export, an import or a large run that goes to the queue.

## The modal

`size()` sets the width of the modal: `small` (400 px), `medium` (520 px), `large` (720 px), or `sm`, `md`, `lg`, `xl`, `2xl`, `3xl`, `4xl`, `5xl`, `6xl` or `7xl`. Without it a confirm is `small` and an action with fields is `medium`. The title is the confirm text, or the action's name when there is none. `fullscreen()` fills the screen, which suits a long form.

```php
(new BulkEdit)->size('4xl')
(new ConfigureIntegration)->fullscreen()
```

## Menu presentation

```php
(new SendWelcomeEmail)->group('Communication')   // a heading in the menu
(new SendWelcomeEmail)->icon('mail')             // a Lucide icon name, in kebab case
(new GenerateReport)->description('Writes a CSV of every record')
(new CreateInvoice)->pinned()                    // a toolbar button
```

The menu lists standalone actions first, then the others by group, then destructive actions below a separator. A pinned action becomes a toolbar button once a row is ticked.

## Attributes

Each of these has an attribute you can put on the class. Fluent calls made after construction win.

| Attribute | Same as |
|---|---|
| `#[Confirmable('Are you sure?')]` | `confirmable()`. The text is optional. |
| `#[Destructive]` | A destructive action. |
| `#[ShowOnIndex(false)]`, `#[ShowOnDetail(false)]` | `exceptOnIndex()`, `exceptOnDetail()`. |
| `#[ShowInline]` | `showInline()`. |
| `#[Standalone]`, `#[Sole]` | `standalone()`, `sole()`. |
| `#[ModalSize('4xl')]` | `size()`. |
| `#[ModalStyle('fullscreen')]` | `fullscreen()`. |
| `#[Pinned]` | `pinned()`. |
| `#[WithoutActionEvents]` | `withoutActionEvents()`. |

They live in `Lathe\Attributes\Action`.

## Short actions

`Action::using()` builds a standalone action from a closure, with no confirmation:

```php
use Lathe\Actions\Action;

Action::using('Clear cache', function (ActionFields $fields, Collection $models) {
    Cache::flush();

    return ActionResult::message('Cache cleared.');
})
```

The other factories build an action that only returns one result: `Action::redirect()`, `Action::visit()`, `Action::openInNewTab()`, `Action::downloadURL()`, `Action::modal()` and `Action::danger()`.

```php
Action::redirect('Open the dashboard', '/lathe')
Action::openInNewTab('View invoice', fn () => 'https://example.com/invoice.pdf')
```

Two more helpers: `handleUsing()` swaps in a handler at runtime, and `noop()` makes an action that only returns "No operation performed." A closure action cannot be queued. Write a class for that.

## Several rows at once

Lathe runs `handle()` once for each selected row, each in its own database transaction. A row that throws rolls back alone, and the others still run. Lathe reports the failure in the response, and the first 50 failures come back with the row id and the message.

- If every row succeeds, `handleResult()` builds the result. By default it returns the last row's result. Override it to combine them.
- If some rows fail, the user sees "N succeeded, M failed."
- If every row fails, the user sees "Action failed for all selected resources."

```php
public function handleResult(ActionFields $fields, array $results): ActionResult
{
    return ActionResult::message(count($results).' records processed.');
}
```

Define `failed()` to react to a failed row. It follows the same naming as `handle()`: `failedForOrders`, `failedForOrder`, then `failed`.

```php
public function failed(Throwable $e, mixed $model): void
{
    logger()->warning("Row {$model->getKey()} failed: {$e->getMessage()}");
}
```

Call `then()` when registering an action to run code once, after every row succeeded. It gets the models:

```php
(new SendWelcomeEmail)->then(fn (Collection $models) => logger("Sent {$models->count()} emails."))
```

`then()` does not run when any row failed, and it does not run for queued runs.

A user who ticks every row ("select all matching") sends the filters instead of ids. Lathe reads the matching rows in chunks, so the selection can hold more rows than fit in memory. A selection of ticked rows may hold up to `limits.id_list_max` (1,000) ids.

## Queued actions

A run is queued in two cases:

- the action implements `ShouldQueue`, or
- the selection holds more rows than `limits.bulk_inline_max` (500 by default). A Resource changes that number with `bulkInlineMax()`.

A queued run needs a queue worker. Without one, nothing runs. See [Deployment](../getting-started/deployment.md).

Lathe splits the selection into chunks of 200 rows and runs one job per chunk. Change the size with the static `$chunkCount`. Unlike an inline run, a chunk calls `handle()` once with all its models, inside one transaction. If it throws, the whole chunk rolls back and the action's `tries` apply.

```php
use Illuminate\Contracts\Queue\ShouldQueue;

class GenerateReport extends Action implements ShouldQueue
{
    public static int $chunkCount = 100;

    public ?string $connection = 'redis';

    public ?string $queue = 'reports';

    public ?int $timeout = 300;

    public ?int $tries = 3;

    // ...
}
```

The user sees "This action is being processed..." and a progress toast, with a Cancel button. Cancelling stops later chunks. The audit log shows the status of every row.

A queued action cannot have a file field, because a job cannot carry an upload. Lathe refuses it when it builds the Resource. A non-queued action with a file field may run on at most `limits.bulk_inline_max` rows.

Queued code does not see the request. The job carries the user, the guard, the locale and the time zone.

### Batches

Implement `Lathe\Actions\BatchableAction` to add your own `then`, `catch` and `finally` callbacks to the batch of a queued run:

```php
use Illuminate\Bus\PendingBatch;
use Lathe\Actions\BatchableAction;

class ProcessImages extends Action implements ShouldQueue, BatchableAction
{
    public function withBatch(ActionFields $fields, PendingBatch $batch): void
    {
        $batch->finally(fn () => logger('All images processed.'));
    }

    // ...
}
```

### Row status

`markAsFinished($model)` and `markAsFailed($model, $e)` update the audit row of one model in the current run. A row that is already finished or failed stays as it is. After the chunk, Lathe marks the rows that are still running as finished.

```php
foreach ($models as $model) {
    try {
        $model->processPayment();
        $this->markAsFinished($model);
    } catch (Throwable $e) {
        $this->markAsFailed($model, $e);
    }
}
```

## Permissions

`canSee()` hides an action. A hidden action answers 403 when called. `canSeeWhen()` takes a gate ability:

```php
(new SuspendUser)->canSee(fn ($request) => $request->user()->isAdmin())
(new SuspendUser)->canSeeWhen('suspend-users')
```

To decide per row, use `canRunWhen()`. It receives the model and decides alone. A row it refuses is skipped, and its button is disabled in the table. `canRun()` also gets the request:

```php
(new ActivateUser)->canRunWhen(fn ($user) => $user->status === 'pending')
```

Without either callback, Lathe asks the model's policy for `runAction`. A destructive action also asks for `runDestructiveAction`.

```php
use Lathe\Actions\Action;

public function runAction(User $user, UserModel $model, Action $action): bool
{
    return true;
}

public function runDestructiveAction(User $user, UserModel $model, Action $action): bool
{
    return $user->isAdmin();
}
```

A policy that lacks the method denies the action. There is no fallback to `update` or `delete`. See [Policies](../security/policies.md). If none of the selected rows may run, the request answers 403.

## Audit log

Every action run writes events to the audit log: who ran it, on which rows, with which field values, and whether each row finished. Add the `Lathe\ActionEvents\Actionable` trait to a model to see its events on the detail page. `withoutActionEvents()` or `#[WithoutActionEvents]` turns recording off for one action. See [Audit log](audit-log.md).

## Pivot actions

Actions on `belongsToMany` and `morphToMany` relation fields run on the pivot rows. See [Relationships](../your-first-resource/relationships.md).

## Test an action

Run an action in a test through the Resource tester. See [Testing](../your-first-resource/testing.md).

## What you see

Tick some rows and open the Actions menu in the toolbar. It lists your actions: standalone ones first, then grouped ones, then destructive ones in red. Pick one and a modal asks for confirmation or shows its form. After you run it, a toast shows the result, and the table updates. When the run is queued, a progress toast counts the rows as the worker finishes them.
