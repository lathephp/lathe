import { afterEach, describe, expect, it, vi } from 'vitest';
import { __panel } from '../index';

afterEach(() => {
  vi.restoreAllMocks();
  __panel.toasts.set([]);
});

describe('loadModules', () => {
  it('names each failed plugin and shows one toast for the page load', async () => {
    const log = vi.spyOn(console, 'error').mockImplementation(() => undefined);
    const importer = (url: string) => (url.includes('good') ? Promise.resolve({}) : Promise.reject(new Error('404')));

    const failed = await __panel.loadModules(
      ['/lathe/modules/good-11111111.js', '/lathe/modules/heartbeat-3f9a1c2e.js', '/lathe/modules/maps-22222222.js'],
      { importer },
    );

    expect(failed).toEqual(['heartbeat', 'maps']);
    expect(log).toHaveBeenCalledWith('[lathe] plugin "heartbeat" failed to load');
    expect(log).toHaveBeenCalledWith('[lathe] plugin "maps" failed to load');
    expect(__panel.toasts.get()).toHaveLength(1);
  });

  it('treats a module that does not answer in time as failed', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => undefined);
    const never = () => new Promise<never>(() => undefined);

    const failed = await __panel.loadModules(['/lathe/modules/slow-33333333.js'], { importer: never, timeoutMs: 20 });

    expect(failed).toEqual(['slow']);
  });
});
