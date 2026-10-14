<?php

namespace Lathe\Dashboards;

use Illuminate\Http\Request;
use Lathe\Exceptions\LatheException;

/**
 * The dashboards registered with the panel. The built-in Main stands in at the key main
 * until a registered dashboard takes it.
 */
class DashboardRegistry
{
    /** @var array<string, Dashboard> */
    protected array $dashboards = [];

    /**
     * @param  Dashboard|class-string<Dashboard>  ...$dashboards
     */
    public function register(Dashboard|string ...$dashboards): void
    {
        foreach ($dashboards as $dashboard) {
            $dashboard = is_string($dashboard) ? $dashboard::make() : $dashboard;
            $key = $dashboard::uriKey();
            $registered = $this->dashboards[$key] ?? null;

            if ($registered !== null && $registered::class !== $dashboard::class) {
                throw new LatheException(sprintf(
                    'Dashboards %s and %s share the key "%s". Give one of them a distinct uriKey().',
                    $registered::class, $dashboard::class, $key,
                ));
            }

            $this->dashboards[$key] ??= $dashboard;
        }
    }

    public function forKey(string $uriKey): ?Dashboard
    {
        return $uriKey === 'main' ? $this->main() : $this->dashboards[$uriKey] ?? null;
    }

    /**
     * The dashboard at the panel home.
     */
    public function main(): Dashboard
    {
        return $this->dashboards['main'] ?? new Main;
    }

    /**
     * Every dashboard, the one at the key main first.
     *
     * @return list<Dashboard>
     */
    public function all(): array
    {
        $main = $this->main();

        return [$main, ...array_values(array_filter($this->dashboards, fn (Dashboard $dashboard) => $dashboard !== $main))];
    }

    /**
     * @return list<Dashboard>
     */
    public function authorized(Request $request): array
    {
        return array_values(array_filter($this->all(), fn (Dashboard $dashboard) => $dashboard->authorize($request)));
    }

    public function flush(): void
    {
        $this->dashboards = [];
    }
}
