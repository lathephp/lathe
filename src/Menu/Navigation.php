<?php

namespace Lathe\Menu;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Lathe\Auth\FortifyConfig;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Dashboards\Dashboard;
use Lathe\Dashboards\DashboardRegistry;
use Lathe\Enums\Icon;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Plugins\PluginRegistry;
use Lathe\Resources\ResourceRegistry;
use Throwable;

/**
 * Builds the sidebar: the dashboards, a Navigation group for the app's loose menu entries,
 * the app's sections, the plugins' sections, then one section per Resource group, read
 * from the manifest without building Resource definitions.
 */
class Navigation
{
    public function __construct(
        protected ResourceRegistry $resources,
        protected PluginRegistry $plugins,
        protected ResourceAuthorization $authorization,
        protected DashboardRegistry $dashboards,
        protected MenuRegistry $menus,
    ) {
        //
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function build(Request $request): array
    {
        return $this->assemble($request)['sections'];
    }

    /**
     * The values of the lazy item badges, keyed by item href. Each value is cached per user
     * for the badge's TTL, so a page view within it runs no badge query.
     *
     * @return array<string, array{value: string, type: string, tooltip?: string}>
     */
    public function badges(Request $request): array
    {
        $guard = app(FortifyConfig::class)->guard();
        $user = $request->user($guard)?->getAuthIdentifier();
        $badges = [];

        foreach ($this->assemble($request)['items'] as $item) {
            $lazy = $item->lazyBadge();

            if ($lazy === null) {
                continue;
            }

            $resolve = fn () => (new Badge($lazy['value'], $lazy['type']))->jsonSerialize();
            $key = 'lathe:menu-badge:'.sha1($item->href()).':'.$guard.':'.(is_scalar($user) ? $user : '');

            // A badge that throws is reported and left off; it never takes the page down.
            try {
                $badges[$item->href()] = $lazy['ttl'] > 0 ? Cache::remember($key, $lazy['ttl'], $resolve) : $resolve();
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $badges;
    }

    /**
     * The sidebar JSON and the visible items behind it, built once per request.
     *
     * @return array{sections: list<array<string, mixed>>, items: list<MenuItem>}
     */
    protected function assemble(Request $request): array
    {
        /** @var array{sections: list<array<string, mixed>>, items: list<MenuItem>}|null $built */
        $built = $request->attributes->get('lathe.navigation');

        if ($built !== null) {
            return $built;
        }

        $latheRequest = $request instanceof LatheRequest ? $request : LatheRequest::createFrom($request);
        $menu = new Menu($request);

        if ($this->menus->main !== null) {
            ($this->menus->main)($request, $menu);
        }

        $loose = array_values(array_filter($menu->entries(), fn (MenuItem|MenuGroup|MenuSection $entry) => ! $entry instanceof MenuSection));
        $sections = [
            ...array_values(array_filter($menu->entries(), fn (MenuItem|MenuGroup|MenuSection $entry) => $entry instanceof MenuSection)),
            ...$this->plugins->menus($request),
        ];

        $explicit = [...$this->visible($loose, $request), ...$this->visible($sections, $request)];
        $taken = array_map(fn (MenuItem $item) => $item->href(), $explicit);

        // An explicit menu item wins over the automatic entry for the same target.
        $automatic = fn (MenuItem $item) => ! in_array($item->href(), $taken, true);

        $dashboards = array_values(array_filter(array_map(
            fn (Dashboard $dashboard) => $dashboard->menu($latheRequest),
            $this->dashboards->authorized($request),
        ), $automatic));

        $navigation = [];

        if ($dashboards !== []) {
            $navigation[] = MenuSection::section(Lathe::__('Dashboards'), $dashboards)->toArray($request);
        }

        $group = MenuSection::section(Lathe::__('Navigation'), $loose)->toArray($request);

        if ($group['items'] !== []) {
            $navigation[] = $group;
        }

        array_push($navigation, ...Menu::arrange($sections, $request));

        $groups = [];

        foreach ($this->resources->manifest() as $entry) {
            if (! $entry['displayInNavigation'] || ! $this->authorization->allowsViewAnyFromManifest($request, $entry)) {
                continue;
            }

            $item = $entry['class']::menu($latheRequest) ?? MenuItem::link($entry['label'], 'resources/'.$entry['uriKey'])
                ->icon($entry['icon'] !== null ? Icon::tryFrom(Str::kebab($entry['icon'])) : null);

            if ($automatic($item)) {
                $groups[$entry['group'] ?? Lathe::__('Resources')][$entry['label']] = $item;
            }
        }

        $resources = [];

        foreach ($groups as $label => $items) {
            ksort($items);
            $section = MenuSection::section((string) $label, array_values($items));
            $resources[] = $section;
            $navigation[] = $section->toArray($request);
        }

        $built = [
            'sections' => $navigation,
            'items' => [...$dashboards, ...$explicit, ...$this->visible($resources, $request)],
        ];

        $request->attributes->set('lathe.navigation', $built);

        return $built;
    }

    /**
     * The items the request may see, inside sections and groups too.
     *
     * @param  list<MenuItem|MenuGroup|MenuSection>  $entries
     * @return list<MenuItem>
     */
    protected function visible(array $entries, Request $request): array
    {
        $items = [];

        foreach ($entries as $entry) {
            if (! $entry->authorized($request)) {
                continue;
            }

            if ($entry instanceof MenuItem) {
                $items[] = $entry;
            } else {
                array_push($items, ...$this->visible($entry->entries(), $request));
            }
        }

        return $items;
    }
}
