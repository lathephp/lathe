<?php

namespace Lathe\Console;

use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Lathe\Doctor\CheckResult;
use Lathe\Doctor\Doctor;
use Lathe\Plugins\Plugin;
use Lathe\Plugins\PluginRegistry;

class PluginsCommand extends Command
{
    protected $signature = 'lathe:plugins {--diagnose : Re-check requirements, ranges and module manifests, and exit 1 on a gap}';

    protected $description = 'List the registered Lathe plugins';

    public function handle(PluginRegistry $plugins, Doctor $doctor): int
    {
        if ($this->option('diagnose')) {
            return $this->diagnose($doctor);
        }

        $packages = $doctor->pluginPackages();
        $failing = $this->failingClasses($doctor);
        $rows = [];

        foreach ($plugins->all() as $plugin) {
            $package = $packages[$plugin::class]['name'] ?? null;
            $rows[] = [
                $plugin->name(),
                $plugin::class,
                $package !== null && InstalledVersions::isInstalled($package) ? (string) InstalledVersions::getPrettyVersion($package) : 'app',
                in_array($plugin::class, $failing, true) ? 'fail' : 'ok',
            ];

            if ($this->output->isVerbose()) {
                $rows[] = ['', 'resources: '.($this->joined($plugin->resources()) ?: '-'), '', ''];
                $rows[] = ['', 'widgets: '.($this->joined(array_map(fn (object $widget) => $widget::class, $plugin->widgets())) ?: '-'), '', ''];
                $rows[] = ['', 'menu: '.($this->menuLabels($plugins, $plugin) ?: '-'), '', ''];
            }
        }

        if ($rows === []) {
            $this->components->info('No plugins are registered.');

            return self::SUCCESS;
        }

        $this->table(['Name', 'Class', 'Version', 'Status'], $rows);

        return self::SUCCESS;
    }

    protected function diagnose(Doctor $doctor): int
    {
        $failed = false;

        foreach ($doctor->pluginChecks() as $result) {
            $this->line(sprintf('%-4s %s', $result->status, $result->message));
            $failed = $failed || $result->status === CheckResult::FAIL;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    protected function failingClasses(Doctor $doctor): array
    {
        $classes = [];

        foreach ($doctor->pluginChecks() as $result) {
            if ($result->status === CheckResult::FAIL && preg_match('/^Plugin (\S+)/', $result->message, $match) === 1) {
                $classes[] = $match[1];
            }
        }

        return $classes;
    }

    /**
     * @param  list<string>  $names
     */
    protected function joined(array $names): string
    {
        return implode(', ', $names);
    }

    /**
     * The labels of the plugin's sidebar entries, read as an admin request would build them.
     */
    protected function menuLabels(PluginRegistry $plugins, Plugin $plugin): string
    {
        $labels = [];
        $request = Request::create('/');
        $menu = $plugin->menu($request);

        if ($menu === null && $plugin->settings() !== null) {
            return 'Settings';
        }

        foreach ($menu ?? [] as $section) {
            foreach ((array) ($section->jsonSerialize()['items'] ?? []) as $item) {
                $labels[] = is_array($item) && is_string($item['label'] ?? null) ? $item['label'] : '';
            }
        }

        return $this->joined(array_values(array_filter($labels)));
    }
}
