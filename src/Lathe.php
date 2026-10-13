<?php

namespace Lathe;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Lathe\Support\ConfigStore config()
 * @method static \Lathe\Resources\ResourceRegistry resources()
 * @method static \Lathe\Plugins\PluginRegistry plugins()
 * @method static \Lathe\Dashboards\DashboardRegistry dashboards()
 * @method static \Lathe\Assets\AssetManifest assets()
 * @method static \Lathe\Auth\FortifyConfig fortify()
 * @method static string name()
 * @method static string version()
 * @method static string path()
 * @method static string loginPath()
 *
 * @see LatheManager
 */
class Lathe extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LatheManager::class;
    }
}
