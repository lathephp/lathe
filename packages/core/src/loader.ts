import { toast } from './toast';
import { __ } from './translation';

export interface LoadOptions {
  timeoutMs?: number;
  importer?: (url: string) => Promise<unknown>;
}

/** heartbeat-3f9a1c2e.js names the plugin "heartbeat". */
export function moduleName(url: string): string {
  const file = url.split('/').pop() ?? url;

  return file.replace(/-[0-9a-f]{8}\.js$/, '').replace(/\.js$/, '');
}

function withTimeout<T>(promise: Promise<T>, ms: number): Promise<T> {
  return new Promise<T>((resolve, reject) => {
    const timer = setTimeout(() => {
      reject(new Error(`timed out after ${String(ms)} ms`));
    }, ms);
    promise.then(
      (value) => {
        clearTimeout(timer);
        resolve(value);
      },
      (error: unknown) => {
        clearTimeout(timer);
        reject(error instanceof Error ? error : new Error(String(error)));
      },
    );
  });
}

/**
 * Import every plugin module before first paint. A module that fails or takes longer
 * than the timeout is logged by name; one toast covers the whole page load.
 */
export async function loadModules(urls: string[], options: LoadOptions = {}): Promise<string[]> {
  const importer = options.importer ?? ((url: string) => import(/* @vite-ignore */ url));
  const results = await Promise.allSettled(urls.map((url) => withTimeout(importer(url), options.timeoutMs ?? 10_000)));
  const failed = urls.filter((_, index) => results[index]?.status === 'rejected').map(moduleName);

  failed.forEach((name) => {
    console.error(`[lathe] plugin "${name}" failed to load`);
  });

  if (failed.length > 0) {
    toast.error(__('A plugin failed to load. The rest of the panel works.'));
  }

  return failed;
}
