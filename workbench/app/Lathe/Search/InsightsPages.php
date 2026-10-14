<?php

namespace Workbench\App\Lathe\Search;

use Illuminate\Support\Str;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Search\GlobalSearchResult;
use Lathe\Search\SearchProvider;
use Workbench\App\Lathe\Plugins\InsightsPlugin;

/**
 * Finds the InsightsPlugin's Overview page in the command palette for whoever may see the plugin.
 */
class InsightsPages extends SearchProvider
{
    public function __construct(protected InsightsPlugin $plugin)
    {
        //
    }

    public function title(): string
    {
        return 'Insights';
    }

    public function uriKey(): string
    {
        return 'insights';
    }

    public function authorize(LatheRequest $request): bool
    {
        return $this->plugin->authorize($request);
    }

    public function search(LatheRequest $request): array
    {
        $term = Str::lower(trim($request->string('q')->toString()));

        if ($term === '' || ! Str::contains('overview', $term)) {
            return [];
        }

        return [GlobalSearchResult::make('Overview')->url('/plugins/'.InsightsPlugin::uriKey())->id('overview')];
    }
}
