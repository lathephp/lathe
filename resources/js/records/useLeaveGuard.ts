import { router } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { useEffect } from 'react';

/**
 * Asks before a visit leaves the page while isDirty() holds. Inertia fires "before" ahead of an
 * instant visit's page swap, so a No cancels the visit with the form still on screen. A prefetch
 * and a visit to this same address (a reload) pass; reload and close get the browser's own prompt.
 */
export function useLeaveGuard(isDirty: () => boolean) {
  const { __ } = useTranslation();
  const question = __('Leave this page? Your unsaved changes will be lost.');

  useEffect(() => {
    const offBefore = router.on('before', (event) => {
      const { visit } = event.detail;
      const here = visit.url.pathname === window.location.pathname && visit.url.search === window.location.search;

      if (isDirty() && !visit.prefetch && !here && !window.confirm(question)) {
        return false;
      }

      return undefined;
    });
    const onUnload = (event: BeforeUnloadEvent) => {
      if (isDirty()) {
        event.preventDefault();
      }
    };
    window.addEventListener('beforeunload', onUnload);

    return () => {
      offBefore();
      window.removeEventListener('beforeunload', onUnload);
    };
  }, [isDirty, question]);
}
