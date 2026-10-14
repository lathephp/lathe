<?php

namespace Lathe\Testing;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Lathe\Http\Middleware\HandleInertiaRequests;
use Lathe\Lathe;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends JSON requests to one Resource's panel API. Made by LatheTest::resource().
 */
class ResourceTester
{
    use MakesHttpRequests;

    protected Application $app;

    protected ?string $lens = null;

    public function __construct(protected string $uriKey)
    {
        /** @var Application $app */
        $app = app();
        $this->app = $app;
    }

    /**
     * The index endpoint, or its lens's after forLens().
     *
     * @param  array<string, mixed>  $query
     * @return TestResponse<Response>
     */
    public function index(array $query = []): TestResponse
    {
        return $this->getJson($this->base().($query === [] ? '' : '?'.http_build_query($query)));
    }

    /**
     * The total rows of the index, or of its lens after forLens().
     *
     * @param  array<string, mixed>  $params
     * @return TestResponse<Response>
     */
    public function count(array $params = []): TestResponse
    {
        return $this->getJson($this->base().'/count'.($params === [] ? '' : '?'.http_build_query($params)));
    }

    /**
     * The filters of the index, or of its lens after forLens().
     *
     * @return TestResponse<Response>
     */
    public function filters(): TestResponse
    {
        return $this->getJson($this->base().'/filters');
    }

    /**
     * A record's detail page as Inertia JSON; the record is under props.record.
     *
     * @return TestResponse<Response>
     */
    public function show(string|int $id): TestResponse
    {
        return $this->get($this->page().'/'.$id, $this->inertiaHeaders());
    }

    /**
     * The lenses the user may open, read off the index page as the panel does; they are under props.lenses.
     *
     * @return TestResponse<Response>
     */
    public function lenses(): TestResponse
    {
        return $this->get($this->page(), $this->inertiaHeaders() + [
            'X-Inertia-Partial-Component' => 'Resources/Index',
            'X-Inertia-Partial-Data' => 'lenses',
        ]);
    }

    /**
     * The signed-in user's saved tabs of this Resource.
     *
     * @return TestResponse<Response>
     */
    public function savedTabs(): TestResponse
    {
        return $this->getJson($this->uri().'/saved-tabs');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return TestResponse<Response>
     */
    public function createSavedTab(array $data): TestResponse
    {
        return $this->postJson($this->uri().'/saved-tabs', $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return TestResponse<Response>
     */
    public function updateSavedTab(string|int $id, array $data): TestResponse
    {
        return $this->putJson($this->uri().'/saved-tabs/'.$id, $data);
    }

    /**
     * @return TestResponse<Response>
     */
    public function deleteSavedTab(string|int $id): TestResponse
    {
        return $this->deleteJson($this->uri().'/saved-tabs/'.$id);
    }

    /**
     * Put the user's saved tabs in this order; the list must hold every one of them once.
     *
     * @param  list<string|int>  $order
     * @return TestResponse<Response>
     */
    public function reorderSavedTabs(array $order): TestResponse
    {
        return $this->putJson($this->uri().'/saved-tabs/reorder', ['ids' => $order]);
    }

    /**
     * Create a record.
     *
     * @param  array<string, mixed>  $data
     * @return TestResponse<Response>
     */
    public function store(array $data): TestResponse
    {
        return $this->postJson($this->uri(), $data);
    }

    /**
     * The create form's fields, defaults and layout.
     *
     * @param  array<string, mixed>  $query
     * @return TestResponse<Response>
     */
    public function creationFields(array $query = []): TestResponse
    {
        return $this->getJson($this->uri().'/creation-fields'.($query === [] ? '' : '?'.http_build_query($query)));
    }

    /**
     * The edit form's fields with the record's values and its traffic-cop token.
     *
     * @return TestResponse<Response>
     */
    public function updateFields(string|int $id): TestResponse
    {
        return $this->getJson($this->uri().'/'.$id.'/update-fields');
    }

    /**
     * The replicate form's create fields, filled from a copy of the record.
     *
     * @return TestResponse<Response>
     */
    public function replicateFields(string|int $id): TestResponse
    {
        return $this->getJson($this->uri().'/'.$id.'/replicate-fields');
    }

    /**
     * The peek of a record: title, subtitle, avatar and the peek fields.
     *
     * @return TestResponse<Response>
     */
    public function peek(string|int $id): TestResponse
    {
        return $this->getJson($this->uri().'/'.$id.'/peek');
    }

    /**
     * The preview of a record: title, subtitle, avatar and the preview fields.
     *
     * @return TestResponse<Response>
     */
    public function preview(string|int $id): TestResponse
    {
        return $this->getJson($this->uri().'/'.$id.'/preview');
    }

    /**
     * Update a record.
     *
     * @param  array<string, mixed>  $data
     * @return TestResponse<Response>
     */
    public function update(string|int $id, array $data): TestResponse
    {
        return $this->putJson($this->uri().'/'.$id, $data);
    }

    /**
     * @return TestResponse<Response>
     */
    public function delete(string|int $id): TestResponse
    {
        return $this->deleteJson($this->uri().'/'.$id);
    }

    /**
     * @return TestResponse<Response>
     */
    public function forceDelete(string|int $id): TestResponse
    {
        return $this->deleteJson($this->uri().'/'.$id.'/force');
    }

    /**
     * @return TestResponse<Response>
     */
    public function restore(string|int $id): TestResponse
    {
        return $this->putJson($this->uri().'/'.$id.'/restore');
    }

    /**
     * The same requests through a lens: its actions and batch routes.
     */
    public function forLens(string $lens): static
    {
        $tester = clone $this;
        $tester->lens = $lens;

        return $tester;
    }

    /**
     * The same requests through a lens, as lens($key)->widgets() reads.
     */
    public function lens(string $lens): static
    {
        return $this->forLens($lens);
    }

    /**
     * The widgets the user may see, without their values; with resourceId, a detail page's.
     *
     * @param  array<string, mixed>  $params
     * @return TestResponse<Response>
     */
    public function widgets(array $params = []): TestResponse
    {
        return $this->getJson($this->base().'/widgets'.($params === [] ? '' : '?'.http_build_query($params)));
    }

    /**
     * One card's value; the params go out as the query string: range, filters and resourceId.
     *
     * @param  array<string, mixed>  $params
     * @return TestResponse<Response>
     */
    public function widget(string $widget, array $params = []): TestResponse
    {
        return $this->getJson($this->base().'/widgets/'.$widget.($params === [] ? '' : '?'.http_build_query($params)));
    }

    /**
     * The visible actions with their metadata, for display index, detail or table-row.
     *
     * @param  array<string, mixed>  $query
     * @return TestResponse<Response>
     */
    public function actions(string $display = 'index', array $query = []): TestResponse
    {
        return $this->getJson($this->base().'/actions?'.http_build_query(['display' => $display] + $query));
    }

    /**
     * An action's modal form, resolved once.
     *
     * @param  array<string, mixed>  $query
     * @return TestResponse<Response>
     */
    public function actionForm(string $action, array $query = []): TestResponse
    {
        return $this->getJson($this->base().'/actions/'.$action.'/form'.($query === [] ? '' : '?'.http_build_query($query)));
    }

    /**
     * Open a chunked upload for an action's file field, such as the import's file.
     *
     * @param  array<string, mixed>  $body  name, size and mime of the file
     * @return TestResponse<Response>
     */
    public function startActionUpload(string $action, string $field, array $body): TestResponse
    {
        return $this->postJson($this->uri().'/actions/'.$action.'/uploads/'.$field, $body);
    }

    /**
     * Run an action on a selection, as the modal does: multipart when a field holds an
     * UploadedFile, with the selection as a JSON string, else JSON.
     *
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>  $fields
     * @return TestResponse<Response>
     */
    public function runAction(string $action, array $selection, array $fields = []): TestResponse
    {
        if (! array_any($fields, fn (mixed $value) => $value instanceof UploadedFile)) {
            return $this->postAction($action, $selection, $fields);
        }

        return $this->post($this->base().'/action', [
            'action' => $action,
            'selection' => json_encode($selection, JSON_THROW_ON_ERROR),
            'fields' => $fields,
        ], ['Accept' => 'application/json']);
    }

    /**
     * The dependent-field lookup of an action's modal: the field the user changed and the values.
     *
     * @param  array<string, mixed>  $values
     * @param  list<string>  $dirty
     * @return TestResponse<Response>
     */
    public function actionDependencies(string $action, string $field, array $values = [], array $dirty = []): TestResponse
    {
        return $this->patchJson($this->base().'/action?'.http_build_query(['action' => $action, 'field' => $field]), ['values' => $values, 'dirty' => $dirty]);
    }

    /**
     * One page of the picker of an action's relation field.
     *
     * @param  array<string, mixed>  $query
     * @return TestResponse<Response>
     */
    public function actionAssociatable(string $action, string $field, array $query = []): TestResponse
    {
        return $this->getJson($this->base().'/action/associatable?'.http_build_query(['action' => $action, 'field' => $field] + $query));
    }

    /**
     * Run an action with a JSON body, whatever its fields hold.
     *
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>  $fields
     * @return TestResponse<Response>
     */
    public function postAction(string $action, array $selection, array $fields = []): TestResponse
    {
        return $this->postJson($this->base().'/action', ['action' => $action, 'selection' => $selection, 'fields' => $fields]);
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return TestResponse<Response>
     */
    public function batchDelete(array $selection): TestResponse
    {
        return $this->deleteJson($this->base().'/batch', ['selection' => $selection]);
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return TestResponse<Response>
     */
    public function batchForceDelete(array $selection): TestResponse
    {
        return $this->deleteJson($this->base().'/batch/force', ['selection' => $selection]);
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return TestResponse<Response>
     */
    public function batchRestore(array $selection): TestResponse
    {
        return $this->putJson($this->base().'/batch/restore', ['selection' => $selection]);
    }

    /**
     * One record's relation: its panel rows, attach, detach and pivot forms.
     */
    public function relation(string $relationship, string|int $id): RelationTester
    {
        return new RelationTester($this->uriKey, $id, $relationship);
    }

    protected function uri(): string
    {
        return rtrim(Lathe::path(), '/').'/api/resources/'.$this->uriKey;
    }

    /**
     * The Resource's index page, outside the API.
     */
    protected function page(): string
    {
        return rtrim(Lathe::path(), '/').'/resources/'.$this->uriKey;
    }

    /**
     * Inertia answers a visit without the current asset version with a 409.
     *
     * @return array<string, string>
     */
    protected function inertiaHeaders(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $this->app->make(HandleInertiaRequests::class)->version(request()),
        ];
    }

    /**
     * The Resource's API path, or its lens's after forLens().
     */
    protected function base(): string
    {
        return $this->lens === null ? $this->uri() : $this->uri().'/lenses/'.$this->lens;
    }
}
