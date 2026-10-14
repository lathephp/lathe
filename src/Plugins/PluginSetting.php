<?php

namespace Lathe\Plugins;

use Illuminate\Database\Eloquent\Model;

/**
 * The saved settings of one plugin, keyed by its uriKey. Plugins read and write it through
 * Plugin::setting() and Plugin::saveSettings().
 *
 * @property string $plugin_key
 * @property array<string, mixed>|null $data
 */
class PluginSetting extends Model
{
    protected $table = 'lathe_plugin_settings';

    protected $primaryKey = 'plugin_key';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = ['plugin_key', 'data'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
