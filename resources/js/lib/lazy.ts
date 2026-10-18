import { lazy, type ComponentType, type LazyExoticComponent } from 'react';
import { markBuildStale } from './assets';

type Loaded<P> = { default: ComponentType<P> };

/** Draws nothing where a chunk of a build the server no longer has would have gone. */
function Missing(): null {
  return null;
}

/**
 * React.lazy over a named export, plus preload(). Once the chunk has loaded, a later first
 * render does not suspend, because the loader then hands React an already settled value.
 * A chunk that fails to load means a new build replaced this one: the panel shows its
 * "Lathe was updated" screen instead of throwing, which would unmount the whole page.
 */
export function lazyNamed<P extends object>(load: () => Promise<ComponentType<P>>): LazyExoticComponent<ComponentType<P>> & { preload: () => Promise<void> } {
  let loaded: ComponentType<P> | undefined;

  const settle = async (): Promise<ComponentType<P>> => {
    try {
      loaded ??= await load();

      return loaded;
    } catch {
      markBuildStale();

      return Missing;
    }
  };

  const preload = async () => {
    await settle();
  };

  const component = lazy(() => {
    if (loaded !== undefined) {
      const module: Loaded<P> = { default: loaded };

      // A thenable that settles at once: React reads it as resolved on this render.
      return { then: (resolve: (value: Loaded<P>) => void) => { resolve(module); } } as unknown as Promise<Loaded<P>>;
    }

    return settle().then((found) => ({ default: found }));
  });

  return Object.assign(component, { preload });
}
