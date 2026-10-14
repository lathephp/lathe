import { lazy, type ComponentType, type LazyExoticComponent } from 'react';

type Loaded<P> = { default: ComponentType<P> };

/**
 * React.lazy over a named export, plus preload(). Once the chunk has loaded, a later first
 * render does not suspend, because the loader then hands React an already settled value.
 */
export function lazyNamed<P extends object>(load: () => Promise<ComponentType<P>>): LazyExoticComponent<ComponentType<P>> & { preload: () => Promise<void> } {
  let loaded: ComponentType<P> | undefined;

  const preload = async () => {
    loaded ??= await load();
  };

  const component = lazy(() => {
    if (loaded !== undefined) {
      const module: Loaded<P> = { default: loaded };

      // A thenable that settles at once: React reads it as resolved on this render.
      return { then: (resolve: (value: Loaded<P>) => void) => { resolve(module); } } as unknown as Promise<Loaded<P>>;
    }

    return load().then((found) => {
      loaded = found;

      return { default: found };
    });
  });

  return Object.assign(component, { preload });
}
