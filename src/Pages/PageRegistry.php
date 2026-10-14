<?php

namespace Lathe\Pages;

use Illuminate\Http\Request;
use Lathe\Exceptions\LatheException;

/**
 * The standalone pages registered with the panel.
 */
class PageRegistry
{
    /** @var array<string, Page> */
    protected array $pages = [];

    /**
     * @param  Page|class-string<Page>  ...$pages
     */
    public function register(Page|string ...$pages): void
    {
        foreach ($pages as $page) {
            $page = is_string($page) ? $page::make() : $page;
            $key = $page::uriKey();
            $registered = $this->pages[$key] ?? null;

            if ($registered !== null && $registered::class !== $page::class) {
                throw new LatheException(sprintf(
                    'Pages %s and %s share the key "%s". Give one of them a distinct uriKey().',
                    $registered::class, $page::class, $key,
                ));
            }

            $this->pages[$key] ??= $page;
        }
    }

    public function find(string $uriKey): ?Page
    {
        return $this->pages[$uriKey] ?? null;
    }

    /**
     * @return list<Page>
     */
    public function all(): array
    {
        return array_values($this->pages);
    }

    /**
     * @return list<Page>
     */
    public function authorized(Request $request): array
    {
        return array_values(array_filter($this->pages, fn (Page $page) => $page->authorize($request)));
    }
}
