import { __panel, pauseShortcuts, resumeShortcuts } from '@lathephp/core';
import { Suspense, useEffect, useId, useRef, useState, type ReactNode } from 'react';
import { lazyNamed } from '../lib/lazy';

// A request that needs the password asks inside the top modal, so two dialogs never stack.
const ConfirmPasswordForm = lazyNamed(() => import('./ConfirmPasswordDialog').then((module) => module.ConfirmPasswordForm));

/** The open modals, bottom first. Only the top one takes keys. */
const stack: string[] = [];

/** At most this many modals open at once; a further one closes as it opens. */
export const MAX_MODALS = 3;

const focusable = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

export function openModalCount(): number {
  return stack.length;
}

interface ModalProps {
  /** The accessible name: the id of the element that titles the modal. */
  labelledBy: string;
  onClose: () => void;
  children: ReactNode;
  className?: string;
  /** While work runs, Escape and the scrim leave the modal open. */
  locked?: boolean;
}

/**
 * A modal on the panel's stack: the top one traps focus and closes on Escape unless locked, focus
 * returns to the element that opened it, shortcuts pause and the page stops scrolling while any is open.
 */
export function Modal({ labelledBy, onClose, children, className, locked = false }: ModalProps) {
  const id = useId();
  const panel = useRef<HTMLDivElement>(null);
  const close = useRef(onClose);
  const held = useRef(locked);
  const [asking, setAsking] = useState<((confirmed: boolean) => void) | null>(null);
  const answer = useRef(asking);

  useEffect(() => {
    // Back from a password check, the focus returns into the modal's own content.
    if (answer.current !== null && asking === null) {
      panel.current?.querySelector<HTMLElement>(focusable)?.focus();
    }

    answer.current = asking;
  }, [asking]);

  useEffect(() => {
    close.current = onClose;
    held.current = locked;
  }, [onClose, locked]);

  useEffect(() => {
    if (stack.length >= MAX_MODALS) {
      close.current();

      return undefined;
    }

    const opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    let pending: Promise<boolean> | undefined;
    const replaced = __panel.setPasswordConfirmer(
      () =>
        (pending ??= new Promise<boolean>((done) => {
          setAsking(() => done);
        }).finally(() => {
          pending = undefined;
        })),
    );
    stack.push(id);
    pauseShortcuts();
    document.body.style.overflow = 'hidden';
    panel.current?.querySelector<HTMLElement>(focusable)?.focus();

    function onKeyDown(event: KeyboardEvent) {
      if (stack.at(-1) !== id) {
        return;
      }

      // Escape during a password check cancels it; a popover inside the modal that handled
      // Escape has closed itself, so the modal stays.
      if (event.key === 'Escape') {
        if (answer.current !== null) {
          event.preventDefault();
          answer.current(false);
          setAsking(null);

          return;
        }

        if (event.defaultPrevented || held.current) {
          return;
        }

        event.preventDefault();
        close.current();
      } else if (event.key === 'Tab' && panel.current !== null) {
        const items = [...panel.current.querySelectorAll<HTMLElement>(focusable)];
        const first = items[0];
        const last = items.at(-1);

        if (first === undefined || last === undefined) {
          event.preventDefault();
        } else if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      }
    }

    document.addEventListener('keydown', onKeyDown);

    return () => {
      document.removeEventListener('keydown', onKeyDown);
      __panel.setPasswordConfirmer(replaced);
      stack.splice(stack.indexOf(id), 1);
      resumeShortcuts();

      if (stack.length === 0) {
        document.body.style.overflow = '';
      }

      opener?.focus();
    };
  }, [id]);

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center p-4 pt-[15vh]">
      <div
        aria-hidden="true"
        className="absolute inset-0 bg-scrim"
        onClick={() => {
          if (!locked) {
            onClose();
          }
        }}
      />
      <div
        ref={panel}
        role="dialog"
        data-lathe-modal
        aria-modal="true"
        aria-labelledby={asking === null ? labelledBy : `${id}-password`}
        className={className ?? 'relative w-full max-w-md space-y-4 rounded-lg border border-border bg-background p-4 text-foreground shadow-lg'}
      >
        {asking === null ? (
          children
        ) : (
          <Suspense fallback={null}>
            <ConfirmPasswordForm
              titleId={`${id}-password`}
              onDone={(confirmed) => {
                asking(confirmed);
                setAsking(null);
              }}
            />
          </Suspense>
        )}
      </div>
    </div>
  );
}
