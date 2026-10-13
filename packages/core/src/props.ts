import { createStore } from './store';

/** The shared Inertia props of the current page, set by the panel on every visit. */
export interface SharedData {
  appName?: string;
  basePath?: string;
  latheVersion?: string;
  assetHash?: string;
  auth?: { user: { name: string; email: string } | null };
  flash?: { success?: string | null; error?: string | null };
  config?: Record<string, unknown>;
  [key: string]: unknown;
}

export const sharedProps = createStore<SharedData>({});

export function basePath(): string {
  return sharedProps.get().basePath ?? '/lathe';
}
