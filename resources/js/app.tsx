import '../css/app.css';
import { createInertiaApp, router } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';
import { StaleAssetScreen } from './components/StaleAssetScreen';
import { Toaster } from './components/Toaster';
import { panelLayout } from './layout/panelLayout';
import { assetHashFromUrl, setEntryHash } from './lib/assets';
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

void createInertiaApp({
  resolve: async (name) => {
    const page = pages[`./pages/${name}.tsx`];

    if (page === undefined) {
      throw new Error(`Lathe has no page named "${name}".`);
    }

    return (await page()).default;
  },
  layout: panelLayout,
  setup({ el, App, props }) {
    __panel.setRouter(router);
    __panel.sharedProps.set(props.initialPage.props);
    showFlash(props.initialPage.props.flash as SharedProps['flash']);
    router.on('navigate', (event) => {
      __panel.sharedProps.set(event.detail.page.props);
    });
    // A visit's response carries its flash once; history navigation replays no toast.
    router.on('success', (event) => {
      showFlash(event.detail.page.props.flash as SharedProps['flash']);
    });

    createRoot(el).render(
      <>
        <App {...props} />
        <Toaster />
        <StaleAssetScreen />
      </>,
    );
  },
});
