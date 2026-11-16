import { useTranslation } from '@lathephp/core';
import { Eye } from 'lucide-react';
import { useState } from 'react';
import { impersonate } from './impersonate';
import { Button } from '../components/ui/Button';

/** The bar over the shell while an admin impersonates someone, with the way back. */
export function ImpersonationBanner({ name, basePath }: { name: string; basePath: string }) {
  const { __ } = useTranslation();
  const [busy, setBusy] = useState(false);

  return (
    <div
      data-lathe-impersonation
      role="status"
      className="flex min-h-10 items-center gap-2.5 bg-warning-subtle px-4 py-1 text-[13px] leading-[18px] text-warning-subtle-foreground"
    >
      <Eye aria-hidden="true" className="size-4 shrink-0" />
      {/* It wraps rather than cuts, so a phone still reads whom the admin acts as. */}
      <span className="min-w-0">{__('You are currently impersonating :name.', { name })}</span>
      <Button
        variant="outline"
        size="sm"
        disabled={busy}
        className="ms-auto h-7 shrink-0"
        onClick={() => {
          setBusy(true);
          impersonate(basePath).catch(() => {
            setBusy(false);
          });
        }}
      >
        {__('Stop Impersonating')}
      </Button>
    </div>
  );
}
