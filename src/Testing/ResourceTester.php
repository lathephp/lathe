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

    protected function uri(): string
    {
        return rtrim(Lathe::path(), '/').'/api/resources/'.$this->uriKey;
    }
}
