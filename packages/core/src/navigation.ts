import { basePath, sharedProps } from './props';

/** The parts of the Inertia router a plugin may use. */
export interface PanelRouter {
  visit: (url: string, options?: Record<string, unknown>) => void;
  reload: (options?: Record<string, unknown>) => void;
}

let current: PanelRouter | null = null;

/** The panel's Inertia router. The panel hands it over at boot, so only one router exists. */
export const router: PanelRouter = {
  visit(url, options) {
    requireRouter().visit(url, options);
  },
  reload(options) {
    requireRouter().reload(options);
  },
};

export function setRouter(next: PanelRouter): void {
  current = next;
}

function requireRouter(): PanelRouter {
  if (current === null) {
    throw new Error('The Lathe router is not ready yet. Call it from a component or a booting() callback.');
  }

  return current;
}

/** A panel URL: url('plugins/heartbeat') is /lathe/plugins/heartbeat. */
export function url(path = ''): string {
  const trimmed = path.replace(/^\/+/, '');

  return trimmed === '' ? basePath() : `${basePath()}/${trimmed}`;
}

/** Go to a panel-relative path through Inertia. */
export function visit(path: string, options?: Record<string, unknown>): void {
  router.visit(url(path), options);
}

/** A value the server provided to scripts, or undefined. */
export function config(key: string): unknown {
  return sharedProps.get().provided?.[key];
}
