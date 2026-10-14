import { pauseShortcuts, resumeShortcuts } from '@lathephp/core';
import { useEffect, useId, useRef, type ReactNode } from 'react';

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
}

/**
 * A modal on the panel's stack: the top one traps focus and closes on Escape, focus returns
 * to the element that opened it, shortcuts pause and the page stops scrolling while any is open.
 */
export function Modal({ labelledBy, onClose, children, className }: ModalProps) {
  const id = useId();
  const panel = useRef<HTMLDivElement>(null);
  const close = useRef(onClose);

  useEffect(() => {
    close.current = onClose;
  }, [onClose]);

  useEffect(() => {
    if (stack.length >= MAX_MODALS) {
      close.current();

      return undefined;
    }

    const opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    stack.push(id);
    pauseShortcuts();
    document.body.style.overflow = 'hidden';
    panel.current?.querySelector<HTMLElement>(focusable)?.focus();

    function onKeyDown(event: KeyboardEvent) {
      if (stack.at(-1) !== id) {
        return;
      }

      if (event.key === 'Escape') {
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
      <div aria-hidden="true" className="absolute inset-0 bg-black/50" onClick={onClose} />
      <div
        ref={panel}
        role="dialog"
        data-lathe-modal
        aria-modal="true"
        aria-labelledby={labelledBy}
        className={className ?? 'relative w-full max-w-md space-y-4 rounded-lg border border-border bg-background p-4 text-foreground shadow-lg'}
      >
        {children}
      </div>
    </div>
  );
}
