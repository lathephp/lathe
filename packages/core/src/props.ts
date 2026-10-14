import { createStore } from './store';

/** The shared Inertia props of the current page, set by the panel on every visit. */
export interface SharedData {
  /** keyboard_shortcuts.enabled; absent means on. */
  keyboardShortcuts?: boolean;
  appName?: string;
  basePath?: string;
  latheVersion?: string;
  assetHash?: string;
  auth?: { user: { name: string; email: string } | null };
  flash?: { success?: string | null; error?: string | null };
  notifications?: { enabled: boolean; pollingInterval: number; showUnreadCount: boolean };
  /** What the server gave Lathe::provideToScript(), read with config(key). */
  provided?: Record<string, unknown>;
  [key: string]: unknown;
}

export const sharedProps = createStore<SharedData>({});

export function basePath(): string {
  return sharedProps.get().basePath ?? '/lathe';
}
