import '../css/app.css';
import { createInertiaApp, router } from '@inertiajs/react';
import { __panel, QueryClientProvider, queryClient } from '@lathephp/core';
import { lazy } from 'react';
import { createRoot } from 'react-dom/client';
import { StaleAssetScreen } from './components/StaleAssetScreen';
import { registerBuiltinFields } from './fields/builtin';
import { AppLayout } from './layout/AppLayout';
import { RunToastsHost } from './actions/RunToastsHost';
import { PasswordConfirmHost } from './components/PasswordConfirmHost';
import { Toaster } from './components/Toaster';
import { panelLayout } from './layout/panelLayout';
import { assetHashFromUrl, markBuildStale, setEntryHash } from './lib/assets';
// Registers the write listener that drops a resource's prefetched pages.
import './lib/instant';
import { DirectionProvider } from './lib/direction';
import { pages, preloadPanelPages } from './lib/pages';
import { pageTitle } from './lib/title';
import { showFlash } from './lib/visits';
import type { SharedProps } from './types';


// Inertia's asset version is off, so the panel compares the server's asset hash with its own.
setEntryHash(assetHashFromUrl(import.meta.url));

// Plugin modules load and register before the first page renders.
const modules = document.getElementById('lathe-modules')?.textContent ?? '[]';
await __panel.loadModules(JSON.parse(modules) as string[]);
registerBuiltinFields();
// Plugins render the panel's own components through core's proxies. The shell is already
// loaded; the rest come in one chunk on first use.
const lent = () => import('./lib/lent');
__panel.registerComponents({
  AppLayout,
  ResourceTable: lazy(() => lent().then((module) => ({ default: module.ResourceTable }))),
  FilterMenu: lazy(() => lent().then((module) => ({ default: module.FilterMenu }))),
  ActionDropdown: lazy(() => lent().then((module) => ({ default: module.ActionDropdown }))),
  DetailFieldPanel: lazy(() => lent().then((module) => ({ default: module.DetailFieldPanel }))),
  DetailHeader: lazy(() => lent().then((module) => ({ default: module.DetailHeader }))),
  WidgetGrid: lazy(() => lent().then((module) => ({ default: module.WidgetGrid }))),
  WidgetCard: lazy(() => lent().then((module) => ({ default: module.WidgetCard }))),
  Excerpt: lazy(() => lent().then((module) => ({ default: module.Excerpt }))),
});
__panel.boot();

// The server's CSP nonce, read off our own entry tag, so Inertia's progress-bar style carries it too.
const nonce = document.querySelector<HTMLScriptElement>('script[type="module"][nonce]')?.nonce;

void createInertiaApp({
  nonce: nonce === '' ? undefined : nonce,
  // The bar shows only for foreground work that takes longer than 150 ms.
  progress: { delay: 150 },
  title: (title) => pageTitle(title, __panel.sharedProps.get().appName ?? 'Lathe'),
  resolve: async (name) => {
    const page = pages[`../pages/${name}.tsx`];

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
    showFlash(props.initialPage.props.flash as SharedProps['flash']);
    // useNavigationProgress follows the visits a person waits for, never a prefetch or a poll.
    router.on('start', (event) => {
      if (!event.detail.visit.prefetch && !event.detail.visit.async) {
        __panel.navigating.set(true);
      }
    });
    router.on('finish', (event) => {
      if (!event.detail.visit.prefetch && !event.detail.visit.async) {
        __panel.navigating.set(false);
      }
    });
    router.on('navigate', (event) => {
      __panel.sharedProps.set(event.detail.page.props);
    });
    // A visit's response carries its flash once; history navigation replays no toast.
    router.on('success', (event) => {
      showFlash(event.detail.page.props.flash as SharedProps['flash']);
    });

    createRoot(el).render(
      <QueryClientProvider client={queryClient}>
        <DirectionProvider direction={props.initialPage.props.rtl === true ? 'rtl' : 'ltr'}>
          <App {...props} />
          <Toaster />
          <RunToastsHost />
          <PasswordConfirmHost />
          <StaleAssetScreen />
        </DirectionProvider>
      </QueryClientProvider>,
    );
    // While the browser is idle, the pages a click most often opens load, so the first click on
    // a sidebar link or a row has no chunk to wait for before it shows the page.
    if ('requestIdleCallback' in window) {
      window.requestIdleCallback(() => {
        void preloadPanelPages();
      });
    } else {
      setTimeout(() => {
        void preloadPanelPages();
      }, 1000);
    }
  },
});
