import { lazy, Suspense, useEffect, useSyncExternalStore } from 'react';
import { listenForRuns, runStore } from './runStore';

// The toasts load when the first run starts, so a page that starts none never fetches them.
const RunToasts = lazy(() => import('./RunToasts').then((module) => ({ default: module.RunToasts })));

/** Listens for the runs the page starts and shows their progress toasts. */
export function RunToastsHost() {
  const runs = useSyncExternalStore(runStore.subscribe, runStore.get);

  useEffect(() => listenForRuns(), []);

  return runs.length === 0 ? null : (
    <Suspense fallback={null}>
      <RunToasts />
    </Suspense>
  );
}
