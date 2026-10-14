<?php

namespace Lathe\Assets;

use Illuminate\Support\Facades\Vite;
use Lathe\Plugins\PluginRegistry;
use Lathe\Support\ConfigStore;
use Lathe\Theme\ThemeStyleGenerator;

/**
 * The tags the root view writes through @latheStyles and @latheScripts, each with the CSP nonce.
 */
class RootTags
{
    public function __construct(
        protected AssetManifest $assets,
        protected PluginRegistry $plugins,
        protected ConfigStore $config,
        protected ThemeStyleGenerator $theme,
    ) {
        //
    }

    public function styles(): string
    {
        $urls = $this->assets->stylesheetUrls($this->prefix());
        $style = $this->assets->styleUrl($this->prefix(), $this->config->nullableString('theme.style') ?? 'nova');

        if ($style !== null) {
            $urls[] = $style;
        }

        $tags = array_map(fn (string $url) => '<link rel="stylesheet" href="'.e($url).'"'.$this->nonce().'>', $urls);

        // After the links, so the config overrides win over the stylesheet defaults.
        $colors = $this->config->get('theme.colors');
        $tags[] = '<style'.$this->nonce().'>'.$this->theme->generate($this->config->nullableString('theme.radius') ?? '0.625rem', is_array($colors) ? $colors : []).'</style>';

        return implode("\n", $tags);
    }

    public function scripts(): string
    {
        $tags = ['<script type="importmap"'.$this->nonce().'>'.json_encode(['imports' => $this->assets->importMap($this->prefix())], JSON_UNESCAPED_SLASHES).'</script>'];
        $modules = $this->plugins->moduleUrls();

        foreach ($modules as $url) {
            $tags[] = '<link rel="modulepreload" href="'.e($url).'"'.$this->nonce().'>';
        }

        $tags[] = '<script type="application/json" id="lathe-modules"'.$this->nonce().'>'.json_encode($modules, JSON_UNESCAPED_SLASHES).'</script>';

        $entry = $this->assets->entryUrl($this->prefix());

        if ($entry !== null) {
            $tags[] = '<script type="module" src="'.e($entry).'"'.$this->nonce().'></script>';
        }

        return implode("\n", $tags);
    }

    protected function prefix(): string
    {
        return '/'.trim($this->config->string('path'), '/');
    }

    protected function nonce(): string
    {
        $nonce = Vite::cspNonce();

        return $nonce === null ? '' : ' nonce="'.e($nonce).'"';
    }
}
