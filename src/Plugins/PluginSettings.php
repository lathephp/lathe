<?php

namespace Lathe\Plugins;

/**
 * The saved settings of each plugin, read once per request. The container binds it scoped,
 * so Octane drops it after every request and a save in one worker reaches the next request.
 */
class PluginSettings
{
    /** @var array<string, array<string, mixed>> */
    protected array $loaded = [];

    /**
     * @return array<string, mixed>
     */
    public function for(string $pluginKey): array
    {
        return $this->loaded[$pluginKey] ??= PluginSetting::find($pluginKey)->data ?? [];
    }

    /**
     * Merge the values into the stored row. A null value removes its key.
     *
     * @param  array<string, mixed>  $values
     */
    public function save(string $pluginKey, array $values): void
    {
        $row = PluginSetting::firstOrNew(['plugin_key' => $pluginKey]);
        $row->data = array_filter([...$row->data ?? [], ...$values], fn (mixed $value) => $value !== null);
        $row->save();

        $this->loaded[$pluginKey] = $row->data;
    }
}
