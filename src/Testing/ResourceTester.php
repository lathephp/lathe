<?php

namespace Lathe\Testing;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
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
     * The index endpoint.
     *
     * @param  array<string, mixed>  $query
     * @return TestResponse<Response>
     */
    public function index(array $query = []): TestResponse
    {
        return $this->getJson($this->uri().($query === [] ? '' : '?'.http_build_query($query)));
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
     * The Resource's API path, or its lens's after forLens().
     */
    protected function base(): string
    {
        return $this->lens === null ? $this->uri() : $this->uri().'/lenses/'.$this->lens;
    }
}
