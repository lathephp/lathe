<?php

namespace Lathe\Menu;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Enums\Icon;
use Lathe\Plugins\PluginRegistry;
use Lathe\Resources\ResourceRegistry;

/**
 * Builds the sidebar: the dashboard, the authorized plugins' menus, then one section
 * per Resource group, read from the manifest without building Resource definitions.
 */
class Navigation
{
    public function __construct(
        protected ResourceRegistry $resources,
        protected PluginRegistry $plugins,
        protected ResourceAuthorization $authorization,
    ) {
        //
    }

    /**
     * @return list<array{label: string|null, icon: string|null, items: list<array{label: string, href: string, icon: string|null}>}>
     */
    public function build(Request $request): array
    {
        $sections = [MenuSection::section(null, [MenuItem::link('Dashboard', '')->icon(Icon::LayoutDashboard)])];

        foreach ($this->plugins->menus($request) as $section) {
            $sections[] = $section;
        }

        $groups = [];

        foreach ($this->resources->manifest() as $entry) {
            if (! $entry['displayInNavigation'] || ! $this->authorization->allowsViewAnyFromManifest($request, $entry)) {
                continue;
            }

            $groups[$entry['group'] ?? 'Resources'][$entry['label']] = MenuItem::link($entry['label'], 'resources/'.$entry['uriKey'])
                ->icon($entry['icon'] !== null ? Icon::tryFrom(Str::kebab($entry['icon'])) : null);
        }

        foreach ($groups as $label => $items) {
            ksort($items);
            $sections[] = MenuSection::section($label, array_values($items));
        }

        return array_map(fn (MenuSection $section) => $section->jsonSerialize(), $sections);
    }
}
