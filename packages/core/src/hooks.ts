import { useSyncExternalStore } from 'react';
import { config } from './navigation';
import { sharedProps, type SharedData } from './props';
import { __, type Translator } from './translation';

export interface LatheContext {
  basePath: string;
  apiPath: string;
  config: typeof config;
  auth: SharedData['auth'];
}

/** The panel's base paths, the script config and the signed-in user. */
export function useLathe(): LatheContext {
  const props = useSyncExternalStore(sharedProps.subscribe, sharedProps.get);
  const base = props.basePath ?? '/lathe';

  return { basePath: base, apiPath: `${base}/api`, config, auth: props.auth };
}

/** The translation function, over the catalogue the server shares for the user's locale. */
export function useTranslation(): { __: Translator } {
  // Subscribe so a new catalogue re-renders the caller; __ reads the shared data itself.
  useSyncExternalStore(sharedProps.subscribe, sharedProps.get);

  return { __ };
}
