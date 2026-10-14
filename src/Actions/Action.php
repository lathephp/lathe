<?php

namespace Lathe\Actions;

use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use JsonSerializable;
use Lathe\Attributes\Action\ActionAttribute;
use Lathe\Field\FileField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Query\Selection;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * Something staff run on selected rows, on one row or on no rows, with an optional modal
 * form, answering an ActionResult. Class attributes apply first; fluent calls win.
 *
 * @phpstan-consistent-constructor
 */
class Action implements JsonSerializable
{
    /** Red styling, and the Resource's destructive action ability before it runs. */
    public bool $destructive = false;

    /** The queue connection a queued run uses. */
    public ?string $connection = null;

    /** The queue a queued run uses. */
    public ?string $queue = null;

    /** Seconds a queued chunk job may run. */
    public ?int $timeout = null;

    /** Attempts a queued chunk job gets. */
    public ?int $tries = null;

    /** Rows per queued chunk job. */
    public static int $chunkCount = 200;

    protected bool $confirmable = false;

    /** @var string|(Closure(int): string)|null */
    protected string|Closure|null $confirmText = null;

    protected ?string $confirmButtonText = null;

    protected ?string $cancelButtonText = null;

    protected string $size = '2xl';

    /** @var 'window'|'fullscreen' */
    protected string $style = 'window';

    protected bool $standalone = false;

    protected bool $sole = false;

    protected bool $pinned = false;

    protected bool $asBlob = false;

    protected bool $showOnIndex = true;

    protected bool $showOnDetail = true;

    protected bool $showOnTableRow = false;

    protected bool $recordsActionEvents = true;

    protected ?string $name = null;

    protected ?string $uriKey = null;

    protected ?string $group = null;

    protected ?string $icon = null;

    protected ?string $description = null;

    /** @var (Closure(LatheRequest): bool)|null */
    protected ?Closure $seeCallback = null;

    /** @var (Closure(LatheRequest, Model): bool)|null */
    protected ?Closure $runCallback = null;

    /** @var (Closure(Model): bool)|null */
    protected ?Closure $runWhenCallback = null;

    protected ?Closure $handleCallback = null;

    protected ?Closure $thenCallback = null;

    /** The batch id of the current run's action events, set by the dispatcher. */
    protected ?string $actionBatchId = null;

    public function __construct()
    {
        $classes = [];

        for ($reflection = new ReflectionClass($this); $reflection !== false; $reflection = $reflection->getParentClass()) {
            array_unshift($classes, $reflection);
        }

        foreach ($classes as $reflection) {
            foreach ($reflection->getAttributes(ActionAttribute::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                $attribute->newInstance()->apply($this);
            }
        }
    }

    public static function make(mixed ...$args): static
    {
        return new static(...$args);
    }

    // Identity

    public function name(): string
    {
        return $this->name ?? Str::headline(class_basename(static::class));
    }

    public function label(): string
    {
        return $this->name();
    }

    public function singularLabel(): string
    {
        return $this->name();
    }

    public function uriKey(): string
    {
        return $this->uriKey ?? Str::kebab(class_basename(static::class));
    }

    public function getUriKey(): string
    {
        return $this->uriKey();
    }

    public function withName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function withUriKey(string $uriKey): static
    {
        $this->uriKey = $uriKey;

        return $this;
    }

    // Form and handlers

    /**
     * The modal form, built from the same fields as Resource forms.
     *
     * @return list<mixed>
     */
    public function fields(LatheRequest $request): array
    {
        return [];
    }

    public function hasFileField(LatheRequest $request): bool
    {
        foreach ($this->fields($request) as $field) {
            if ($field instanceof FileField) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, Model>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResult
    {
        $result = $this->handleCallback === null ? null : ($this->handleCallback)($fields, $models);

        return $result instanceof ActionResult ? $result : ActionResult::message(Lathe::__('Action was executed successfully.'));
    }

    /**
     * @param  Collection<int, Model>  $models
     */
    public function __invoke(ActionFields $fields, Collection $models): ActionResult
    {
        return $this->handle($fields, $models);
    }

    public function handleUsing(Closure $callback): static
    {
        $this->handleCallback = $callback;

        return $this;
    }

    /**
     * Fold the results of a run into the one the client gets: the last, by default.
     *
     * @param  array<array-key, ActionResult>  $results
     */
    public function handleResult(ActionFields $fields, array $results): ActionResult
    {
        $last = end($results);

        return $last instanceof ActionResult ? $last : ActionResult::message(Lathe::__('Action was executed successfully.'));
    }

    /**
     * Run once after the whole run succeeded, with its models.
     */
    public function then(callable $callback): static
    {
        $this->thenCallback = $callback(...);

        return $this;
    }

    /**
     * @param  Collection<int, Model>  $models
     */
    public function runThen(Collection $models): void
    {
        if ($this->thenCallback !== null) {
            ($this->thenCallback)($models);
        }
    }

    public function noop(): static
    {
        return $this->handleUsing(fn () => ActionResult::message(Lathe::__('No operation performed.')));
    }

    public function afterValidation(LatheRequest $request, Validator $validator): void
    {
        //
    }

    /**
     * The whole run on a selection: authority, fields, handlers, audit rows and the response.
     *
     * @return array{result: ActionResult, permitted: int, skipped: int, failed: int, errors: list<array{id: int|string|null, message: string}>}
     */
    public function handleRequest(LatheRequest $request, Selection $selection): array
    {
        return app(ActionDispatcher::class)->dispatch($request, $this, $selection);
    }

    public function withActionBatchId(?string $batchId): static
    {
        $this->actionBatchId = $batchId;

        return $this;
    }

    /**
     * Marks the current run's event for the model finished; a row already finished or failed stays.
     */
    public function markAsFinished(Model $model): void
    {
        if ($this->actionBatchId !== null) {
            Lathe::actionEventModel()::markAsFinished($this->actionBatchId, $model);
        }
    }

    /**
     * Marks the current run's event for the model failed, with the exception's message.
     */
    public function markAsFailed(Model $model, ?Throwable $exception = null): void
    {
        if ($this->actionBatchId !== null) {
            Lathe::actionEventModel()::markAsFailed($this->actionBatchId, $model, $exception);
        }
    }

    // Authorization

    /**
     * @param  Closure(LatheRequest): bool  $callback
     */
    public function canSee(Closure $callback): static
    {
        $this->seeCallback = $callback;

        return $this;
    }

    public function canSeeWhen(string $ability, mixed $arguments = []): static
    {
        return $this->canSee(fn (LatheRequest $request) => Gate::forUser($request->user())->check($ability, $arguments));
    }

    public function authorizedToSee(LatheRequest $request): bool
    {
        return $this->seeCallback === null || ($this->seeCallback)($request);
    }

    /**
     * @param  Closure(LatheRequest, Model): bool  $callback
     */
    public function canRun(Closure $callback): static
    {
        $this->runCallback = $callback;

        return $this;
    }

    /**
     * Decide alone which rows may run; the Resource ability is not asked.
     *
     * @param  Closure(Model): bool  $callback
     */
    public function canRunWhen(Closure $callback): static
    {
        $this->runWhenCallback = $callback;

        return $this;
    }

    public function hasRunCallback(): bool
    {
        return $this->runCallback !== null || $this->runWhenCallback !== null;
    }

    /**
     * Whether the run callbacks allow the row, canRunWhen first; null when neither is set,
     * so the caller asks the Resource ability.
     */
    public function authorizedToRunOn(LatheRequest $request, Model $model): ?bool
    {
        if ($this->runWhenCallback !== null) {
            return ($this->runWhenCallback)($model);
        }

        return $this->runCallback === null ? null : ($this->runCallback)($request, $model);
    }

    // Where it shows

    public function onlyOnIndex(): static
    {
        return $this->showOn(index: true, detail: false, tableRow: false);
    }

    public function onlyOnDetail(): static
    {
        return $this->showOn(index: false, detail: true, tableRow: false);
    }

    public function exceptOnIndex(): static
    {
        $this->showOnIndex = false;

        return $this;
    }

    public function exceptOnDetail(): static
    {
        $this->showOnDetail = false;

        return $this;
    }

    public function showInline(): static
    {
        $this->showOnTableRow = true;

        return $this;
    }

    public function onlyInline(): static
    {
        return $this->showOn(index: false, detail: false, tableRow: true);
    }

    public function onlyOnTableRow(): static
    {
        return $this->onlyInline();
    }

    public function exceptInline(): static
    {
        $this->showOnTableRow = false;

        return $this;
    }

    public function exceptOnTableRow(): static
    {
        return $this->exceptInline();
    }

    protected function showOn(bool $index, bool $detail, bool $tableRow): static
    {
        $this->showOnIndex = $index;
        $this->showOnDetail = $detail;
        $this->showOnTableRow = $tableRow;

        return $this;
    }

    public function shownOnIndex(): bool
    {
        return $this->showOnIndex;
    }

    public function shownOnDetail(): bool
    {
        return $this->showOnDetail;
    }

    public function shownOnTableRow(): bool
    {
        return $this->showOnTableRow;
    }

    // Kind

    public function standalone(): static
    {
        $this->standalone = true;

        return $this;
    }

    public function sole(): static
    {
        $this->sole = true;

        return $this;
    }

    public function pinned(): static
    {
        $this->pinned = true;

        return $this;
    }

    public function asBlob(): static
    {
        $this->asBlob = true;

        return $this;
    }

    public function withoutActionEvents(): static
    {
        $this->recordsActionEvents = false;

        return $this;
    }

    public function isStandalone(): bool
    {
        return $this->standalone;
    }

    public function isSole(): bool
    {
        return $this->sole;
    }

    public function isDestructive(): bool
    {
        return $this->destructive;
    }

    public function isPinned(): bool
    {
        return $this->pinned;
    }

    public function recordsActionEvents(): bool
    {
        return $this->recordsActionEvents;
    }

    // Confirmation and modal

    /**
     * @param  string|Closure(int): string  $text  ":count" in a string is the selection size
     */
    public function confirmable(string|Closure $text = ''): static
    {
        $this->confirmable = true;

        return $this->confirmText($text);
    }

    /**
     * @param  string|Closure(int): string  $text
     */
    public function confirmText(string|Closure $text): static
    {
        $this->confirmText = $text === '' ? null : $text;

        return $this;
    }

    /**
     * The confirmation text for a run on $count rows: ":count" replaced, a closure called with it.
     */
    public function confirmTextFor(int $count): ?string
    {
        $text = $this->confirmText instanceof Closure ? ($this->confirmText)($count) : $this->confirmText;

        return is_string($text) ? str_replace(':count', number_format($count), $text) : null;
    }

    /**
     * The modal's values, checked by the fields' rules: 422 per field key on failure.
     */
    public function validateFields(LatheRequest $request): ActionFields
    {
        return app(ActionFieldValidator::class)->validate($request, $this);
    }

    public function confirmButtonText(string $text): static
    {
        $this->confirmButtonText = $text;

        return $this;
    }

    public function cancelButtonText(string $text): static
    {
        $this->cancelButtonText = $text;

        return $this;
    }

    public function withoutConfirmation(): static
    {
        $this->confirmable = false;

        return $this;
    }

    public function isConfirmable(): bool
    {
        return $this->confirmable;
    }

    public function size(string $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function fullscreen(bool $fullscreen = true): static
    {
        $this->style = $fullscreen ? 'fullscreen' : 'window';

        return $this;
    }

    // Menu presentation

    public function group(string $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    /**
     * A Lucide icon name.
     */
    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Plain text under the name in the menu.
     */
    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    // Factories: each makes a standalone action that runs without confirmation.

    public static function using(string $name, Closure $handler): static
    {
        return (new static)
            ->withName($name)
            ->withUriKey(Str::kebab($name))
            ->handleUsing($handler)
            ->standalone()
            ->withoutConfirmation();
    }

    /**
     * @param  string|Closure(): string  $url
     */
    public static function redirect(string $name, string|Closure $url): static
    {
        return static::using($name, fn () => ActionResult::redirect(static::target($url)));
    }

    /**
     * @param  string|Closure(): string  $path
     * @param  array<string, mixed>  $options
     */
    public static function visit(string $name, string|Closure $path, array $options = []): static
    {
        return static::using($name, fn () => ActionResult::visit(static::target($path), $options));
    }

    /**
     * @param  string|Closure(): string  $url
     */
    public static function openInNewTab(string $name, string|Closure $url): static
    {
        return static::using($name, fn () => ActionResult::openInNewTab(static::target($url)));
    }

    /**
     * @param  string|Closure(): string  $url
     */
    public static function downloadURL(string $name, string|Closure $url): static
    {
        return static::using($name, fn () => ActionResult::download($name, static::target($url)));
    }

    /**
     * @param  array<string, mixed>|Closure(): array<string, mixed>  $data
     */
    public static function modal(string $name, string $component, array|Closure $data = []): static
    {
        return static::using($name, fn () => ActionResult::modal($component, $data instanceof Closure ? $data() : $data));
    }

    public static function danger(string $name, string $message): static
    {
        return static::using($name, fn () => ActionResult::danger($message));
    }

    public static function message(string $text): ActionResult
    {
        return ActionResult::message($text);
    }

    public static function deleted(): ActionResult
    {
        return ActionResult::deleted();
    }

    /**
     * @param  string|Closure(): string  $value
     */
    protected static function target(string|Closure $value): string
    {
        return $value instanceof Closure ? $value() : $value;
    }

    /**
     * The menu metadata. Fields are not resolved here; a closure confirm text needs the
     * selection count, so the list sends null for it.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'uriKey' => $this->uriKey(),
            'name' => $this->name(),
            'icon' => $this->icon,
            'description' => $this->description,
            'group' => $this->group,
            'destructive' => $this->isDestructive(),
            'standalone' => $this->isStandalone(),
            'sole' => $this->isSole(),
            'pinned' => $this->isPinned(),
            // A class that declares fields gets the modal even when it is not confirmable:
            // run without it, the fields would get no values.
            'withoutConfirmation' => ! $this->isConfirmable() && (new ReflectionMethod($this, 'fields'))->class === self::class,
            'confirmText' => is_string($this->confirmText) ? $this->confirmText : null,
            'confirmButtonText' => $this->confirmButtonText ?? Lathe::__('Run Action'),
            'cancelButtonText' => $this->cancelButtonText ?? Lathe::__('Cancel'),
            'size' => $this->size,
            'style' => $this->style,
            'asBlob' => $this->asBlob,
            'showOnIndex' => $this->shownOnIndex(),
            'showOnDetail' => $this->shownOnDetail(),
            'showOnTableRow' => $this->shownOnTableRow(),
        ];
    }
}
