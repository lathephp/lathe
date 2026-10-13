import { __panel, useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { useSyncExternalStore } from 'react';
import { entryAssetHash, isBuildStale, subscribeToStale } from '../lib/assets';

/**
 * Blocks the panel when the server runs another build than the loaded entry, or when a page
 * of the loaded build fails to load. A reload loads the new build, so the screen only comes
 * back if the mismatch persists.
 */
export function StaleAssetScreen() {
  const { __ } = useTranslation();
  const { assetHash } = useSyncExternalStore(__panel.sharedProps.subscribe, __panel.sharedProps.get);
  const failedToLoad = useSyncExternalStore(subscribeToStale, isBuildStale);
  const entry = entryAssetHash();
  const mismatch = entry !== null && assetHash !== undefined && assetHash !== entry;

  if (!mismatch && !failedToLoad) {
    return null;
  }

  return (
    <div
      role="alertdialog"
      aria-modal="true"
      aria-labelledby="lathe-stale-title"
      aria-describedby="lathe-stale-text"
      className="fixed inset-0 z-50 grid place-items-center bg-background p-6 text-foreground"
    >
      <div className="max-w-md space-y-4 text-center">
        <h2 id="lathe-stale-title" className="text-xl font-semibold">
          {__('Lathe was updated')}
        </h2>
        <p id="lathe-stale-text" className="text-sm text-muted-foreground">
          {__('If this keeps happening, run php artisan lathe:assets --force')}
        </p>
        <Button
          onClick={() => {
            location.reload();
          }}
        >
          {__('Reload')}
        </Button>
      </div>
    </div>
  );
}
