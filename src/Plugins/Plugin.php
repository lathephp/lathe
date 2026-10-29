<?php

namespace Lathe\Plugins;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Lathe\Auth\FortifyConfig;
use Lathe\Field\BooleanField;
use Lathe\Field\Field;
use Lathe\Field\FieldCollection;
use Lathe\Field\FieldContext;
use Lathe\Field\NumericField;
use Lathe\Field\TextField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Layout\SectionPresenter;
use Lathe\Menu\MenuSection;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceRegistry;
use Lathe\Widgets\Widget;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A package or in-app class that adds pages, routes and scripts to the panel.
 *
 * @phpstan-consistent-constructor
 */
abstract class Plugin
{
    /** @var (Closure(Request): bool)|null */
    protected ?Closure $seeCallback = null;

    public static function make(): static
    {
        return new static;
    }

    public function name(): string
    {
        return Str::headline(Str::beforeLast(class_basename(static::class), 'Plugin') ?: class_basename(static::class));
    }

    public static function uriKey(): string
    {
        $base = class_basename(static::class);

        return Str::kebab(Str::endsWith($base, 'Plugin') && $base !== 'Plugin' ? Str::beforeLast($base, 'Plugin') : $base);
    }

    /**
     * @param  Closure(Request): bool  $callback
     */
    public function canSee(Closure $callback): static
    {
        $this->seeCallback = $callback;

        return $this;
    }

    public function canSeeWhen(string $ability, mixed $arguments = []): static
    {
        return $this->canSee(fn (Request $request) => Gate::forUser($request->user(app(FortifyConfig::class)->guard()))->check($ability, $arguments));
    }

    public function authorize(Request $request): bool
    {
        return $this->seeCallback === null || ($this->seeCallback)($request);
    }

    /**
     * Classes that must exist for this plugin to work.
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return [];
    }

    /**
     * Policies to register, as model class => policy class.
     *
     * @return array<class-string, class-string>
     */
    public function policies(): array
    {
        return [];
    }

    /**
     * Resources boot() registers.
     *
     * @return list<class-string<\Lathe\Resources\Resource>>
     */
    public function resources(): array
    {
        return [];
    }

    /**
     * Widgets a host may spread into its own widgets(); a plugin mounts none itself.
     *
     * @return list<Widget>
     */
    public function widgets(): array
    {
        return [];
    }

    /**
     * Middleware appended to the lathe group, so it runs on every panel request.
     *
     * @return list<string>
     */
    public function middleware(): array
    {
        return [];
    }

    /**
     * The settings form, in a Resource's fields() schema; null means no settings page,
     * route or storage.
     *
     * @return list<mixed>|null
     */
    public function settings(): ?array
    {
        return null;
    }

    /**
     * One saved setting, read from the store once per request.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return app(PluginSettings::class)->for(static::uriKey())[$key] ?? $default;
    }

    /**
     * Merge the values into the saved settings; a null value clears its key.
     *
     * @param  array<string, mixed>  $values
     */
    public function saveSettings(array $values): void
    {
        app(PluginSettings::class)->save(static::uriKey(), $values);
    }

    /**
     * Answers GET and PUT on the settings path, without going through handleRequest(): GET
     * returns the form and the saved values, PUT validates and saves the declared fields.
     */
    public function handleSettingsRequest(Request $request): Response
    {
        $items = $this->settings();

        if ($items === null) {
            throw new NotFoundHttpException;
        }

        $request = $request instanceof LatheRequest ? $request : LatheRequest::createFrom($request);
        $fields = SectionPresenter::flattenFields($items, 'Details');

        if ($request->isMethod('GET')) {
            return new JsonResponse([
                'fields' => array_map(fn (Field $field) => $field->schema(FieldContext::Update, $request), $fields),
                'layout' => SectionPresenter::layout($items, new FieldCollection($fields), $request, FieldContext::Update),
                'values' => (object) [...$this->settingDefaults($fields, $request), ...app(PluginSettings::class)->for(static::uriKey())],
            ]);
        }

        if (! $request->isMethod('PUT')) {
            throw new MethodNotAllowedHttpException(['GET', 'PUT']);
        }

        $rules = $messages = $attributes = [];

        foreach ($fields as $field) {
            $rules = [...$rules, ...$this->settingRules($field, $request)];
            $messages = [...$messages, ...$field->getMessages()];
            $attributes = [...$attributes, ...$field->getValidationAttributes()];
        }

        Validator::make($request->all(), $rules, $messages, $attributes)->validate();

        // Each field fills the values the way it fills a record, so a boolean is stored as true or false.
        $filled = new stdClass;
        $values = [];

        foreach ($fields as $field) {
            if ($request->has($field->attribute())) {
                $field->fill($request, $filled);
                $values[$field->attribute()] = $this->settingValue($field, data_get($filled, $field->attribute()));
            }
        }

        $this->saveSettings($values);

        return new JsonResponse(['saved' => true]);
    }

    /**
     * Each field's default, so the form shows it until a value is saved. A checkbox's implicit
     * false draws the same as no value, so it is left out.
     *
     * @param  list<Field>  $fields
     * @return array<string, mixed>
     */
    private function settingDefaults(array $fields, LatheRequest $request): array
    {
        $defaults = [];

        foreach ($fields as $field) {
            $default = $field->resolveDefaultValue($request);

            if ($default !== null && $default !== false) {
                $defaults[$field->attribute()] = $default;
            }
        }

        return $defaults;
    }

    /**
     * The field's rules plus the type a setting must have, since no model column checks it:
     * a text field takes a string and a boolean field a boolean (a number field implies numeric).
     *
     * @return array<string, list<mixed>>
     */
    private function settingRules(Field $field, LatheRequest $request): array
    {
        $rules = $field->getRules(FieldContext::Update, $request);
        $type = match (true) {
            $field instanceof TextField => 'string',
            $field instanceof BooleanField => 'boolean',
            default => null,
        };

        // A translatable text field takes an array of locales and checks each one on its own key.
        if ($type !== null && isset($rules[$field->attribute()]) && ! in_array('array', $rules[$field->attribute()], true)) {
            $rules[$field->attribute()][] = $type;
        }

        return $rules;
    }

    /**
     * A number field's value the form sent as a string, stored as the number, as a model cast would.
     */
    private function settingValue(Field $field, mixed $value): mixed
    {
        return $field instanceof NumericField && is_string($value) && is_numeric($value) ? $value + 0 : $value;
    }

    /**
     * Scripts and styles, as ['scripts' => [name => path], 'styles' => [name => path]].
     *
     * @return array{scripts?: array<string, string>, styles?: array<string, string>}
     */
    public function assets(): array
    {
        return [];
    }

    /**
     * Runs once per process, when the plugin is registered.
     */
    public function register(): void
    {
        foreach ($this->policies() as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }

    /**
     * Runs once per process, right after register().
     */
    public function boot(): void
    {
        if ($this->resources() !== []) {
            app(ResourceRegistry::class)->register(...$this->resources());
        }
    }

    /**
     * Runs on every panel request this plugin is authorized for.
     */
    public function serving(Request $request): void
    {
        //
    }

    /**
     * Sidebar sections for this plugin: null adds a Settings link when settings() is set,
     * [] adds none, and a list is used as it is.
     *
     * @return list<MenuSection>|null
     */
    public function menu(Request $request): ?array
    {
        return null;
    }

    /**
     * Package routes, mounted under {path}/api/plugins/{uriKey}/ before the catch-all.
     */
    public function routes(Router $router): void
    {
        //
    }

    /**
     * Answers the plugin API catch-all. Unknown paths and verbs are 404.
     */
    public function handleRequest(Request $request, string $path): Response
    {
        throw new NotFoundHttpException;
    }
}
