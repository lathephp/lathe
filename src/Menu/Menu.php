<?php

namespace Lathe\Menu;

use Closure;
use Illuminate\Http\Request;
use JsonSerializable;
use Lathe\Plugins\PluginRegistry;

/**
 * The menu an app builds in its Menu::using callback, as the sidebar renders it.
 *
 * @phpstan-consistent-constructor
 */
class Menu implements JsonSerializable
{
    /** @var list<MenuItem|MenuGroup|MenuSection> */
    protected array $items = [];

    public function __construct(protected Request $request)
    {
        //
    }

    /**
     * Register the callback that builds the main menu. It receives the request and the Menu.
     *
     * @param  Closure(Request, Menu): mixed  $callback
     */
    public static function using(Closure $callback): void
    {
        app(MenuRegistry::class)->main = $callback;
    }

    /**
     * @param  Closure(Request, Menu): mixed  $callback
     */
    public static function mainMenu(Closure $callback): void
    {
        static::using($callback);
    }

    /**
     * Register the callback that builds the user menu in the sidebar footer.
     *
     * @param  Closure(Request, Menu): mixed  $callback
     */
    public static function userMenu(Closure $callback): void
    {
        app(MenuRegistry::class)->user = $callback;
    }

    /**
     * The menus of the authorized plugins, then whatever the main callback adds.
     */
    public static function build(Request $request): static
    {
        $menu = new static($request);

        foreach (app(PluginRegistry::class)->menus($request) as $section) {
            $menu->add($section);
        }

        $callback = app(MenuRegistry::class)->main;

        if ($callback !== null) {
            $callback($request, $menu);
        }

        return $menu;
    }

    public static function buildUserMenu(Request $request): static
    {
        $menu = new static($request);
        $callback = app(MenuRegistry::class)->user;

        if ($callback !== null) {
            $callback($request, $menu);
        }

        return $menu;
    }

    public function add(MenuItem|MenuGroup|MenuSection ...$items): static
    {
        array_push($this->items, ...$items);

        return $this;
    }

    public function append(MenuItem|MenuGroup|MenuSection ...$items): static
    {
        return $this->add(...$items);
    }

    public function prepend(MenuItem|MenuGroup|MenuSection ...$items): static
    {
        $this->items = array_values([...$items, ...$this->items]);

        return $this;
    }

    /**
     * The entries the request may see, by ascending sort. Entries with an equal sort keep
     * the order they were added in.
     *
     * @param  list<MenuItem|MenuGroup|MenuSection>  $entries
     * @return list<array<string, mixed>>
     */
    public static function arrange(array $entries, Request $request): array
    {
        $visible = array_values(array_filter($entries, fn (MenuItem|MenuGroup|MenuSection $entry) => $entry->authorized($request)));

        // usort is stable since PHP 8.0, so ties keep their insertion order.
        usort($visible, fn (MenuItem|MenuGroup|MenuSection $a, MenuItem|MenuGroup|MenuSection $b) => $a->sortOrder() <=> $b->sortOrder());

        return array_map(fn (MenuItem|MenuGroup|MenuSection $entry) => $entry->toArray($request), $visible);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function jsonSerialize(): array
    {
        return static::arrange($this->items, $this->request);
    }
}
