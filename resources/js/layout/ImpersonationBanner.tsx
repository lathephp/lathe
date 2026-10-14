import { useTranslation } from '@lathephp/core';
import { useState } from 'react';
import { impersonate } from './impersonate';

/** The bar over the shell while an admin impersonates someone, with the way back. */
export function ImpersonationBanner({ name, basePath }: { name: string; basePath: string }) {
  const { __ } = useTranslation();
  const [busy, setBusy] = useState(false);

  return (
    <div data-lathe-impersonation role="status" className="flex h-10 items-center justify-between gap-4 bg-foreground px-4 text-sm text-background">
      <span className="truncate">{__('You are currently impersonating :name.', { name })}</span>
      <button
        type="button"
        disabled={busy}
        className="shrink-0 rounded-md px-2 py-1 font-medium underline outline-none focus-visible:ring-2 focus-visible:ring-background"
        onClick={() => {
          setBusy(true);
          impersonate(basePath).catch(() => {
            setBusy(false);
          });
        }}
      >
        {__('Stop Impersonating')}
      </button>
    </div>
  );
}
