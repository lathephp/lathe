<?php

namespace Lathe\Testing;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
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

    protected function uri(): string
    {
        return rtrim(Lathe::path(), '/').'/api/resources/'.$this->uriKey;
    }
}
