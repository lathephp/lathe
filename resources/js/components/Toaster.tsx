import { __panel, useTranslation } from '@lathephp/core';
import { X } from 'lucide-react';
import { Suspense, useState, useSyncExternalStore } from 'react';
import { cn } from '../lib/cn';
import { lazyNamed } from '../lib/lazy';

// Base UI Toast loads with the first toast, off the first paint. After a deploy the old
// build's chunk is gone; then the plain list draws the toasts instead.
const ToastStack = lazyNamed(() =>
  import('./ToastStack').then(
    (module) => module.ToastStack,
    () => PlainToasts,
  ),
);

/** The toasts of the panel store, at the bottom end of the screen. */
export function Toaster() {
  const toasts = useSyncExternalStore(__panel.toasts.subscribe, __panel.toasts.get);
  const [started, setStarted] = useState(false);

  if (!started && toasts.length > 0) {
    setStarted(true);
  }

  if (!started) {
    return null;
  }

  return (
    <Suspense fallback={null}>
      <ToastStack />
    </Suspense>
  );
}

/** The toasts as plain markup, for when the Base UI chunk fails to load. */
function PlainToasts() {
  const { __ } = useTranslation();
  const toasts = useSyncExternalStore(__panel.toasts.subscribe, __panel.toasts.get);

  if (toasts.length === 0) {
    return null;
  }

  return (
    <section aria-label={__('Notifications')} className="fixed end-4 bottom-4 z-40 flex w-80 flex-col gap-2">
      {toasts.map((toast) => (
        <div
          key={toast.id}
          role={toast.type === 'error' ? 'alert' : 'status'}
          className={cn(
            'flex items-start gap-3 rounded-lg border bg-background p-3 text-sm shadow-lg',
            toast.type === 'error' ? 'border-destructive text-destructive' : 'border-border',
          )}
        >
          <p className="flex-1">{toast.message}</p>
          {toast.links?.map((link) => (
            <a
              key={link.href}
              href={link.href}
              {...(link.newTab ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
              className="rounded-sm font-medium underline outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
              {link.label}
            </a>
          ))}
          {toast.action !== undefined ? (
            <button
              type="button"
              className="rounded-sm font-medium underline outline-none focus-visible:ring-2 focus-visible:ring-ring"
              onClick={() => {
                __panel.dismissToast(toast.id);
                toast.action?.onClick();
              }}
            >
              {toast.action.label}
            </button>
          ) : null}
          <button
            type="button"
            aria-label={__('Dismiss')}
            className="rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
            onClick={() => {
              __panel.dismissToast(toast.id);
            }}
          >
            <X aria-hidden="true" className="size-4" />
          </button>
        </div>
      ))}
    </section>
  );
}
