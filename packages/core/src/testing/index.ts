import { sharedProps, type SharedData } from '../props';

// The published @lathephp/core throws on load outside the panel. A plugin's Vitest config
// aliases @lathephp/core to this entry, which is the same API without the throw.
export * from '../index';

/**
 * Stand in for the panel's shared props: the base path, the signed-in user, the script
 * config, and a locale with its catalogue. Call it with nothing to reset.
 */
export function mockLathe(data: SharedData = {}): void {
  sharedProps.set({ basePath: '/lathe', auth: { user: null }, ...data });
}
