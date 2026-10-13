<?php

namespace Lathe\Http\Payloads;

use Illuminate\Database\Eloquent\Model;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\TrafficCop;
use Lathe\Support\ConfigStore;

/**
 * The detail page and the create and edit forms of one record, shared by the pages and the JSON API.
 */
class RecordPayload
{
    /** @var list<string> */
    public const array ABILITIES = ['view', 'update', 'delete', 'replicate', 'restore', 'forceDelete'];

    public function __construct(
        protected LatheRequest $request,
        protected ResourceAuthorization $authorization,
        protected ConfigStore $config,
    ) {
        //
    }

    /**
     * The detail page's record: its fields with their values, the layout and the abilities.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return array{id: mixed, title: string, subtitle: string, avatar: null, fields: list<array<string, mixed>>, layout: list<array<string, mixed>>, abilities: array<string, bool>, trashed: bool}
     */
    public function detail(string $class, Model $model): array
    {
        $resource = $class::make($model);
        $user = $this->request->user();

        return [
            'id' => $model->getKey(),
            'title' => $resource->title(),
            'subtitle' => $resource->subtitle(),
            'avatar' => null,
            'fields' => array_map(
                fn (Field $field) => [...$field->schema(FieldContext::Detail, $this->request, $model), ...$field->cell($model)],
                $this->fields($class, FieldContext::Detail, $model),
            ),
            'layout' => $resource->layoutFor(FieldContext::Detail, $this->request, $model),
            'abilities' => array_combine(self::ABILITIES, array_map(
                fn (string $ability) => $this->authorization->allows($user, $class, $ability, $model),
                self::ABILITIES,
            )),
            'trashed' => method_exists($model, 'trashed') && $model->trashed() === true,
        ];
    }

    /**
     * The create form: the fields, each holding its default, the defaults and the layout.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return array{fields: list<array<string, mixed>>, defaults: array<string, mixed>, layout: list<array<string, mixed>>, borderless: bool}
     */
    public function creationFields(string $class): array
    {
        $resource = $class::make();
        $fields = $this->fields($class, FieldContext::Create);
        $defaults = $class::defaultAttributes($this->request);

        // A field default wins over defaultAttributes(); a field without one keeps it.
        foreach ($fields as $field) {
            $default = $field->resolveDefaultValue($this->request);

            if ($default !== null) {
                $defaults[$field->key()] = $default;
            }
        }

        return [
            'fields' => array_map(
                fn (Field $field) => [...$field->schema(FieldContext::Create, $this->request), 'value' => $defaults[$field->key()] ?? null],
                $fields,
            ),
            'defaults' => $defaults,
            'layout' => $resource->layoutFor(FieldContext::Create, $this->request),
            'borderless' => $resource->definition()->isBorderless(),
        ];
    }

    /**
     * The edit form: the fields with their stored values, the layout and the traffic-cop token.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return array{fields: list<array<string, mixed>>, layout: list<array<string, mixed>>, updatedAt: string|null}
     */
    public function updateFields(string $class, Model $model): array
    {
        return [
            'fields' => array_map(
                fn (Field $field) => [...$field->schema(FieldContext::Update, $this->request, $model), 'value' => $field->resolve($model)],
                $this->fields($class, FieldContext::Update, $model),
            ),
            'layout' => $class::make($model)->layoutFor(FieldContext::Update, $this->request, $model),
            'updatedAt' => TrafficCop::token($model),
        ];
    }

    /**
     * Dashboard / Resource, then the given tail.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @param  list<array{label: string, href: string|null}>  $tail
     * @return list<array{label: string, href: string|null}>
     */
    public function breadcrumbs(string $class, array $tail): array
    {
        return [
            ['label' => Lathe::__('Dashboard'), 'href' => $this->href('')],
            ['label' => $class::label(), 'href' => $this->href('resources/'.$class::uriKey())],
            ...$tail,
        ];
    }

    /**
     * A panel URL from a path inside the panel.
     */
    public function href(string $path): string
    {
        $base = '/'.trim($this->config->string('path'), '/');

        return $path === '' ? $base : rtrim($base, '/').'/'.$path;
    }

    /**
     * The fields of a context this request may see. ?only=a,b narrows them first, so no
     * other field's closure runs.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return list<Field>
     */
    protected function fields(string $class, FieldContext $context, ?Model $model = null): array
    {
        $only = $this->request->query('only');
        $keys = is_string($only) ? array_values(array_filter(explode(',', $only), fn (string $key) => $key !== '')) : null;

        return array_values($class::make($model)->fieldsFor($context, $this->request)
            ->onlyKeys($keys)
            ->forContext($context, $this->request, $model)
            ->authorized($this->request)
            ->all());
    }
}
