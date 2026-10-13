import type { ComponentType, LazyExoticComponent } from 'react';

/** The props a plugin page receives. */
export interface PluginPageProps {
  pluginKey: string;
  basePath: string;
  path?: string;
}

/** A page component; pass React.lazy(() => import('./Page')) to load it on first use. */
export type ComponentOrLazy<P> = ComponentType<P> | LazyExoticComponent<ComponentType<P>>;

const pluginPages = new Map<string, ComponentOrLazy<PluginPageProps>>();
const bootingCallbacks: (() => void)[] = [];
let booted = false;

/** Register the page shown at /lathe/plugins/{uriKey}. The last registration wins. */
export function plugin(uriKey: string, page: ComponentOrLazy<PluginPageProps>): void {
  pluginPages.set(uriKey, page);
}

export function pluginPage(uriKey: string): ComponentOrLazy<PluginPageProps> | undefined {
  return pluginPages.get(uriKey);
}

/** Run a callback before first paint, or at once when the panel has already booted. */
export function booting(callback: () => void): void {
  if (booted) {
    callback();
  } else {
    bootingCallbacks.push(callback);
  }
}

export function boot(): void {
  booted = true;
  bootingCallbacks.splice(0).forEach((callback) => {
    callback();
  });
}
