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
        protected AssetRegistry $registry,
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

        array_push($urls, ...$this->registry->styleUrls($this->prefix()));

        $tags = array_map(fn (string $url) => '<link rel="stylesheet" href="'.e($url).'"'.$this->nonce().'>', $urls);

        // After the links, so the config overrides win over the stylesheet defaults.
        $colors = $this->config->get('theme.colors');
        $tags[] = '<style'.$this->nonce().'>'.$this->theme->generate($this->config->nullableString('theme.radius') ?? '0.625rem', is_array($colors) ? $colors : []).'</style>';

        // Before the entry module, so the dark class is on <html> before first paint.
        $tags[] = '<script'.$this->nonce().'>'.$this->appearanceScript().'</script>';

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

    /**
     * Sets the dark class from the stored choice, or the configured default when storage is blocked or empty.
     * The choice also sits in data-lathe-appearance so the client reads it when storage is blocked, and the
     * listener keeps System in step with the OS while the menu is closed.
     */
    protected function appearanceScript(): string
    {
        $default = $this->config->nullableString('theme.appearance');
        $default = in_array($default, ['light', 'dark', 'system'], true) ? $default : 'system';

        return '(function(){var d='.json_encode($default).',r=document.documentElement,a,q=matchMedia("(prefers-color-scheme: dark)");'
            ."try{a=localStorage.getItem('lathe-appearance')}catch(e){}"
            .'if(a!=="light"&&a!=="dark"&&a!=="system"){a=d}'
            .'r.dataset.latheAppearance=a;'
            .'function f(){var k=r.dataset.latheAppearance,x=k==="dark"||(k==="system"&&q.matches);r.classList.toggle("dark",x);r.classList.toggle("light",!x)}'
            .'f();q.addEventListener("change",f)})()';
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
