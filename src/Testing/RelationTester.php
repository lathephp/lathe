<?php

namespace Lathe\Testing;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Testing\TestResponse;
use Lathe\Lathe;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends JSON requests to one relation of one record. Made by ResourceTester::relation().
 */
class RelationTester
{
    use MakesHttpRequests;

    protected Application $app;

    public function __construct(protected string $uriKey, protected string|int $id, protected string $relationship)
    {
        /** @var Application $app */
        $app = app();
        $this->app = $app;
    }

    /**
     * The related rows, as the relation panel reads them.
     *
     * @param  array<string, mixed>  $params
     * @return TestResponse<Response>
     */
    public function index(array $params = []): TestResponse
    {
        return $this->getJson($this->uri().($params === [] ? '' : '?'.http_build_query($params)));
    }

    /**
     * @param  array<string, mixed>  $pivot
     * @return TestResponse<Response>
     */
    public function attach(string|int $relatedId, array $pivot = []): TestResponse
    {
        return $this->postJson($this->uri(), ['related_id' => $relatedId, 'pivot' => $pivot]);
    }

    /**
     * @return TestResponse<Response>
     */
    public function detach(string|int $relatedId): TestResponse
    {
        return $this->deleteJson($this->uri().'/'.$relatedId);
    }

    /**
     * @param  array<string, mixed>  $pivot
     * @return TestResponse<Response>
     */
    public function updatePivot(string|int $relatedId, array $pivot): TestResponse
    {
        return $this->putJson($this->uri().'/'.$relatedId.'/pivot', ['pivot' => $pivot]);
    }

    /**
     * The attach modal's pivot form.
     *
     * @return TestResponse<Response>
     */
    public function pivotFields(): TestResponse
    {
        return $this->getJson($this->uri().'/pivot-fields');
    }

    /**
     * The pivot form of one attached row, with its values.
     *
     * @return TestResponse<Response>
     */
    public function pivotFieldValues(string|int $relatedId): TestResponse
    {
        return $this->getJson($this->uri().'/'.$relatedId.'/pivot-fields');
    }

    protected function uri(): string
    {
        return rtrim(Lathe::path(), '/').'/api/resources/'.$this->uriKey.'/'.$this->id.'/'.$this->relationship;
    }
}
