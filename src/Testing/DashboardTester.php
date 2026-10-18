<?php

namespace Lathe\Testing;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Testing\TestResponse;
use Lathe\Lathe;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends JSON requests to one dashboard's panel API. Made by LatheTest::dashboard().
 */
class DashboardTester
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
     * The dashboard's name, description, icon and refresh button.
     *
     * @return TestResponse<Response>
     */
    public function show(): TestResponse
    {
        return $this->getJson($this->uri());
    }

    /**
     * The widgets the user may see, without their values.
     *
     * @return TestResponse<Response>
     */
    public function widgets(): TestResponse
    {
        return $this->getJson($this->uri().'/widgets');
    }

    /**
     * One card's value; the params go out as the query string, such as range and filters.
     *
     * @param  array<string, mixed>  $params
     * @return TestResponse<Response>
     */
    public function widget(string $widget, array $params = []): TestResponse
    {
        return $this->getJson($this->uri().'/widgets/'.$widget.($params === [] ? '' : '?'.http_build_query($params)));
    }

    /**
     * One chart or table card as a CSV download; the params go out as the query string.
     *
     * @param  array<string, mixed>  $params
     * @return TestResponse<Response>
     */
    public function csv(string $widget, array $params = []): TestResponse
    {
        return $this->get($this->uri().'/widgets/'.$widget.'/csv'.($params === [] ? '' : '?'.http_build_query($params)));
    }

    /**
     * Saves the user's layout: each widget's uriKey, width and hidden flag, in order.
     *
     * @param  list<array{uriKey: string, width: string, hidden: bool}>  $widgets
     * @return TestResponse<Response>
     */
    public function saveLayout(array $widgets): TestResponse
    {
        return $this->putJson($this->uri().'/layout', ['widgets' => $widgets]);
    }

    /**
     * Drops the user's layout, back to the one in code.
     *
     * @return TestResponse<Response>
     */
    public function resetLayout(): TestResponse
    {
        return $this->deleteJson($this->uri().'/layout');
    }

    protected function uri(): string
    {
        return rtrim(Lathe::path(), '/').'/api/dashboards/'.$this->uriKey;
    }
}
