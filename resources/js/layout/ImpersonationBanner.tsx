import { useTranslation } from '@lathephp/core';
import { Eye } from 'lucide-react';
import { useState } from 'react';
import { impersonate } from './impersonate';

/** The bar over the shell while an admin impersonates someone, with the way back. */
export function ImpersonationBanner({ name, basePath }: { name: string; basePath: string }) {
  const { __ } = useTranslation();
  const [busy, setBusy] = useState(false);

  return (
    <div
      data-lathe-impersonation
      role="status"
      className="flex h-10 items-center gap-2.5 bg-warning-subtle px-4 text-[13px] leading-[18px] text-warning-subtle-foreground"
    >
      <Eye aria-hidden="true" className="size-4 shrink-0" />
      <span className="truncate">{__('You are currently impersonating :name.', { name })}</span>
      <button
        type="button"
        disabled={busy}
        className="ms-auto inline-flex h-7 shrink-0 items-center rounded-md border border-border bg-card px-2.5 font-medium text-foreground outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-focus"
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
