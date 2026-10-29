<?php

namespace Lathe\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lathe\Pages\PageRegistry;
use Lathe\Support\ConfigStore;

class PageController
{
    /**
     * An unknown page answers 404 and one the user may not see 403, both inside the shell. The page's
     * own path travels as pagePath, so the shell's shared basePath keeps the panel root.
     */
    public function __invoke(Request $request, PageRegistry $pages, ConfigStore $config, string $page, string $path = ''): Response
    {
        $found = $pages->find($page);

        abort_if($found === null, 404);
        abort_unless($found->authorize($request), 403);

        return Inertia::render('Page', [
            'pageKey' => $found::uriKey(),
            'pagePath' => '/'.trim($config->string('path'), '/').'/'.$found->path(),
            'path' => $path,
            'title' => $found->name(),
        ]);
    }
}
