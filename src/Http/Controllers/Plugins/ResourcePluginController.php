<?php

namespace Lathe\Http\Controllers\Plugins;

use Lathe\Auth\ResourceAuthorization;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Plugins\ResourcePlugin;
use Lathe\Plugins\ResourcePluginRegistry;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forwards /api/resource-plugins/{component}/{path?}?resource=&resourceId= into the plugin's
 * handleRequest(). The record is re-resolved through the Resource's scoped query and checked
 * for view, so a plugin never trusts an id of its own.
 */
class ResourcePluginController
{
    public function __invoke(LatheRequest $request, ResourcePluginRegistry $plugins, ResourceAuthorization $authorization, string $component, string $path = ''): Response
    {
        $class = $plugins->ownerOf($component);
        $id = $request->query('resourceId');

        abort_if($class === null || $request->query('resource') !== $class::uriKey() || ! is_string($id), 404);

        $plugin = collect(ResourcePluginRegistry::placedIn($class, $request))->first(fn (ResourcePlugin $found) => $found->component() === $component);

        abort_if($plugin === null, 404);
        abort_unless($plugin->authorize($request), 403);

        abort_unless($authorization->canSee($request, $class), 403);
        abort_unless($authorization->allows($request->user(), $class, 'viewAny'), 403, $authorization->denialMessage($class));

        $model = (new ResourceQuery($class::make(), $request, QueryContext::Detail))->find($id);

        abort_unless($authorization->allows($request->user(), $class, 'view', $model), 403, $authorization->denialMessage($class));

        $request->attributes->set(ResourcePlugin::RECORD_ATTRIBUTE, $model);

        return $plugin->handleRequest($request, $path);
    }
}
