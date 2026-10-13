import { setRouter } from './navigation';
import { sharedProps } from './props';
import { boot, pluginPage } from './registry';
import { dismissToast, toasts } from './toast';
import { loadModules } from './loader';
import { shortcutPauseCount } from './shortcuts';
import { setTranslator, translatorFor } from './translation';

/** The plugin API version this package provides. */
export const apiVersion = '1.0.0';

export { booting, plugin } from './registry';
export type { ComponentOrLazy, PluginPageProps } from './registry';
export { config, router, url, visit } from './navigation';
export type { PanelRouter } from './navigation';
export { ApiError, request } from './http';
export type { RequestOptions } from './http';
export { toast } from './toast';
export type { ToastMessage, ToastType } from './toast';
export { emit, off, on, once } from './events';
export { addShortcut, pauseShortcuts, resumeShortcuts } from './shortcuts';
export { QueryClientProvider, queryClient, queryKeys, useQuery, useQueryClient } from './query';
export { useLathe, useTranslation } from './hooks';
export type { LatheContext } from './hooks';
export type { SharedData } from './props';
export type { SafeHtml } from './safe-html';
export type { Translator } from './translation';

/**
 * What the panel itself uses to run the API. Not for plugins.
 *
 * @internal
 */
export const __panel = {
  boot,
  dismissToast,
  loadModules,
  pluginPage,
  setRouter,
  setTranslator,
  sharedProps,
  shortcutPauseCount,
  toasts,
  translatorFor,
};
