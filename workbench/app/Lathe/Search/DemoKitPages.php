<?php

namespace Workbench\App\Lathe\Search;

use Illuminate\Support\Str;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Pages\Page;
use Lathe\Search\GlobalSearchResult;
use Lathe\Search\SearchProvider;
use Workbench\App\Lathe\Pages\SystemStatus;
use Workbench\App\Lathe\Pages\UiGallery;

/**
 * Finds the Demo Kit pages the user may open by name in the command palette.
 */
class DemoKitPages extends SearchProvider
{
    public function title(): string
    {
        return 'Demo Kit';
    }

    public function uriKey(): string
    {
        return 'demo-kit';
    }

    public function authorize(LatheRequest $request): bool
    {
        return true;
    }

    public function search(LatheRequest $request): array
    {
        $term = Str::lower(trim($request->string('q')->toString()));
        $results = [];

        foreach ([UiGallery::class, SystemStatus::class] as $class) {
            $page = Lathe::pages()->find($class::uriKey());

            if ($page instanceof Page && $term !== '' && Str::contains(Str::lower($page->name()), $term) && $page->authorize($request)) {
                $results[] = GlobalSearchResult::make($page->name())->url('/'.$page->path())->id($page::uriKey());
            }
        }

        return $results;
    }
}
