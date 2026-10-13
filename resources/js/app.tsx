import '../css/app.css';
import { createInertiaApp, router } from '@inertiajs/react';
import { __panel, QueryClientProvider, queryClient } from '@lathephp/core';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';
import { StaleAssetScreen } from './components/StaleAssetScreen';
import { Toaster } from './components/Toaster';
import { panelLayout } from './layout/panelLayout';
import { assetHashFromUrl, markBuildStale, setEntryHash } from './lib/assets';
import { pageTitle } from './lib/title';
import { showFlash } from './lib/visits';
import type { SharedProps } from './types';

// Each page is its own lazy chunk.
const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx');

// Inertia's asset version is off, so the panel compares the server's asset hash with its own.
setEntryHash(assetHashFromUrl(import.meta.url));

// Plugin modules load and register before the first page renders.
const modules = document.getElementById('lathe-modules')?.textContent ?? '[]';
await __panel.loadModules(JSON.parse(modules) as string[]);
__panel.boot();

// The server's CSP nonce, read off our own entry tag, so Inertia's progress-bar style carries it too.
const nonce = document.querySelector<HTMLScriptElement>('script[type="module"][nonce]')?.nonce;

void createInertiaApp({
  nonce: nonce === '' ? undefined : nonce,
  // The bar shows only for foreground work that takes longer than 150 ms.
  progress: { delay: 150 },
  title: (title) => pageTitle(title, __panel.sharedProps.get().appName ?? 'Lathe'),
  resolve: async (name) => {
    const page = pages[`./pages/${name}.tsx`];

    if (page === undefined) {
      throw new Error(`Lathe has no page named "${name}".`);
    }

    try {
      return (await page()).default;
    } catch {
      // The old build's chunk is gone after a rebuild: show "Lathe was updated" instead of an error.
      markBuildStale();

      return () => null;
    }
  },
  layout: panelLayout,
  setup({ el, App, props }) {
    __panel.setRouter(router);
    __panel.sharedProps.set(props.initialPage.props);
    __panel.setTranslator(__panel.translatorFor(props.initialPage.props));
    showFlash(props.initialPage.props.flash as SharedProps['flash']);
    router.on('navigate', (event) => {
      __panel.sharedProps.set(event.detail.page.props);
      __panel.setTranslator(__panel.translatorFor(event.detail.page.props));
    });
    // A visit's response carries its flash once; history navigation replays no toast.
    router.on('success', (event) => {
      showFlash(event.detail.page.props.flash as SharedProps['flash']);
    });

    createRoot(el).render(
      <QueryClientProvider client={queryClient}>
        <App {...props} />
        <Toaster />
        <StaleAssetScreen />
      </QueryClientProvider>,
    );
  },
});
