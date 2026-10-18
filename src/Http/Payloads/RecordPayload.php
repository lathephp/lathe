<?php

namespace Lathe\Http\Payloads;

use Illuminate\Database\Eloquent\Model;
use Lathe\ActionEvents\Actionable;
use Lathe\ActionEvents\ActionEventResource;
use Lathe\Assets\AssetRegistry;
use Lathe\Auth\Impersonation;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Field\Relations\RelationField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Plugins\ResourcePlugin;
use Lathe\Plugins\ResourcePluginRegistry;
use Lathe\Resources\RecordHeader;
use Lathe\Resources\Resource;
use Lathe\Resources\TrafficCop;
use Lathe\Resources\Via;
use Lathe\Support\ConfigStore;
use Lathe\Support\ResourceRelationCountPreloader;

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
     * @return array{id: mixed, title: string, subtitle: string, header: array{badge: string|null, meta: string|null}, titleKey: string|null, avatar: array{url: string|null, initials: string|null, style: array<string, mixed>, rounded: bool}|null, fields: list<array<string, mixed>>, counts: array<string, int>, history: bool, layout: list<array<string, mixed>>, resourcePlugins: list<array<string, mixed>>, abilities: array<string, bool>, trashed: bool}
     */
    public function detail(string $class, Model $model): array
    {
        $resource = $class::make($model);
        $user = $this->request->user();
        $fields = $this->fields($class, FieldContext::Detail, $model);
        $title = $resource->definition()->getTitle();
        // One query counts every withCount relation, so a relation tab shows its count before its panel loads.
        $counted = array_values(array_filter($fields, fn (Field $field) => $field instanceof RelationField && $field->showsCount()));
        $cells = array_map(
            fn (Field $field) => [...$field->schema(FieldContext::Detail, $this->request, $model), ...$field->cell($model)],
            $fields,
        );
        ResourceRelationCountPreloader::preload(collect([$model]), array_map(fn (Field $field) => $field->attribute(), $counted));
        $counts = [];

        foreach ($counted as $field) {
            $count = $model->getAttribute($field->attribute().'_count');
            $counts[$field->key()] = is_numeric($count) ? (int) $count : 0;
        }

        return [
            'id' => $model->getKey(),
            'title' => $resource->title(),
            'subtitle' => $resource->subtitle(),
            'header' => RecordHeader::pick($fields, $resource->definition()->getRecordHeader()),
            // The detail page leaves the title field out of its Details card; a closure title names none.
            'titleKey' => is_string($title) ? $title : null,
            'avatar' => $resource->resolveAvatar()?->jsonSerialize(),
            'fields' => $cells,
            'counts' => $counts,
            // The History card reads the same log as the Action Events panel, behind the same gate.
            'history' => in_array(Actionable::class, class_uses_recursive($model), true)
                && $this->authorization->allows($user, ActionEventResource::class, 'viewAny'),
            'layout' => $resource->layoutFor(FieldContext::Detail, $this->request, $model),
            'resourcePlugins' => array_map(
                fn (ResourcePlugin $plugin) => $this->resourcePlugin($class, $plugin),
                array_values(array_filter(ResourcePluginRegistry::placedIn($class, $this->request), fn (ResourcePlugin $plugin) => $plugin->authorize($this->request))),
            ),
            'abilities' => [
                ...array_combine(self::ABILITIES, array_map(
                    fn (string $ability) => $this->authorization->allows($user, $class, $ability, $model),
                    self::ABILITIES,
                )),
                // Not a policy ability: Impersonatable decides. The live request holds the session.
                ...(Impersonation::uses($model) ? ['impersonate' => Impersonation::availableTo(request(), $model)] : []),
            ],
            'trashed' => method_exists($model, 'trashed') && $model->trashed() === true,
        ];
    }

    /**
     * A resource plugin for the client, with the URLs of its scripts when it has any, so the
     * detail page loads them itself: no page load scans the Resources for them.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return array<string, mixed>
     */
    protected function resourcePlugin(string $class, ResourcePlugin $plugin): array
    {
        $prefix = '/'.trim($this->config->string('path'), '/');
        $assets = app(AssetRegistry::class);
        $scripts = [];

        foreach ($plugin->assets()['scripts'] ?? [] as $name => $path) {
            $scripts[] = $assets->resourceScriptUrl($prefix, $name, $path, $class::uriKey());
        }

        return $scripts === [] ? $plugin->jsonSerialize() : [...$plugin->jsonSerialize(), 'scripts' => $scripts];
    }

    /**
     * A record's peek or preview: its heading and the fields shown in that context. Relation
     * fields will be dropped from the default peek list once they exist.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return array{id: mixed, title: string, subtitle: string, avatar: array{url: string|null, initials: string|null, style: array<string, mixed>, rounded: bool}|null, fields: list<array<string, mixed>>}
     */
    public function summary(string $class, Model $model, FieldContext $context): array
    {
        $resource = $class::make($model);

        return [
            'id' => $model->getKey(),
            'title' => $resource->title(),
            'subtitle' => $resource->subtitle(),
            'avatar' => $resource->resolveAvatar()?->jsonSerialize(),
            'fields' => array_map(
                fn (Field $field) => [...$field->schema($context, $this->request, $model), ...$field->cell($model)],
                $this->fields($class, $context, $model),
            ),
        ];
    }

    /**
     * The create form: the fields, each holding its default, the defaults and the layout.
     * Under a via, the field pointing at the parent is locked to it.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return array{fields: list<array<string, mixed>>, defaults: array<string, mixed>, layout: list<array<string, mixed>>, borderless: bool}
     */
    public function creationFields(string $class, ?Via $via = null): array
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

            // The via's parent wins over any default.
            if ($via?->locks($field) === true) {
                $defaults[$field->key()] = $via->value();
            }
        }

        return [
            'fields' => array_map(
                fn (Field $field) => [
                    ...$field->schema(FieldContext::Create, $this->request),
                    'value' => $defaults[$field->key()] ?? null,
                    ...($via?->locks($field) === true ? ['readonly' => true] : []),
                ],
                $fields,
            ),
            'defaults' => $defaults,
            'layout' => $resource->layoutFor(FieldContext::Create, $this->request),
            'borderless' => $resource->definition()->isBorderless(),
        ];
    }

    /**
     * The replicate form: the create form with each value read from a copy of the record.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return array{fields: list<array<string, mixed>>, defaults: array<string, mixed>, layout: list<array<string, mixed>>, borderless: bool}
     */
    public function replicateFields(string $class, Model $model): array
    {
        $replica = $class::replicate($model);
        $resource = $class::make();

        return [
            'fields' => array_map(
                fn (Field $field) => [...$field->schema(FieldContext::Create, $this->request, $replica), 'value' => $field->resolve($replica)],
                $this->fields($class, FieldContext::Create),
            ),
            'defaults' => $class::defaultAttributes($this->request),
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
     * Dashboard / Resource, then the given tail; none when breadcrumbs.enabled is false.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @param  list<array{label: string, href: string|null}>  $tail
     * @return list<array{label: string, href: string|null}>
     */
    public function breadcrumbs(string $class, array $tail): array
    {
        if (! $this->config->boolean('breadcrumbs.enabled')) {
            return [];
        }

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
