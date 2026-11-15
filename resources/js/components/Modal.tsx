import { __panel, pauseShortcuts, resumeShortcuts, useTranslation } from '@lathephp/core';
import { Suspense, useEffect, useId, useLayoutEffect, useRef, useState, type ComponentProps, type ReactNode } from 'react';
import { cn } from '../lib/cn';
import { lazyNamed } from '../lib/lazy';
import { Button } from './ui/Button';

// A request that needs the password asks inside the top modal, so two dialogs never stack.
const ConfirmPasswordForm = lazyNamed(() => import('./ConfirmPasswordDialog').then((module) => module.ConfirmPasswordForm));

/** The open modals, bottom first. Only the top one takes keys. */
const stack: string[] = [];

/** At most this many modals open at once; a further one closes as it opens. */
export const MAX_MODALS = 3;

const focusable = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

// The look of every part is the style's cn-dialog-* rule. The layout every style shares, and the
// phone's bottom sheet, are the [data-lathe-modal] rules in app.css.

/** The title and its description, stacked. */
/**
 * Where the focus goes back to when the dialog closes: what held it as the dialog opened, or,
 * when that was an item of a menu, which goes as the dialog opens, the button of that open menu.
 */
function openerOf(active: Element | null): HTMLElement | null {
  const button = active?.closest('[role=menu]') ? document.querySelector<HTMLElement>('[aria-haspopup=menu][aria-expanded=true]') : null;

  return button ?? (active instanceof HTMLElement ? active : null);
}

export function ModalHeader({ className, ...props }: ComponentProps<'div'>) {
  return <div className={cn('cn-dialog-header flex flex-col', className)} {...props} />;
}

export function ModalTitle({ className, children, ...props }: ComponentProps<'h2'>) {
  return (
    <h2 className={cn('cn-dialog-title', className)} {...props}>
      {children}
    </h2>
  );
}

export function ModalDescription({ className, ...props }: ComponentProps<'p'>) {
  return <p className={cn('cn-dialog-description', className)} {...props} />;
}

/** The buttons, right-aligned with the primary last; on a phone they stack 44 px tall with the primary on top. */
export function ModalFooter({ className, ...props }: ComponentProps<'div'>) {
  return <div className={cn('cn-dialog-footer flex justify-end gap-2', className)} {...props} />;
}

/** The X at the top end. It comes after the content, so the focus still opens on the first field. */
export function ModalClose({ onClick }: { onClick: () => void }) {
  const { __ } = useTranslation();

  return (
    <Button variant="ghost" size="icon-sm" aria-label={__('Close')} className="cn-dialog-close" onClick={onClick}>
      {/* Drawn here rather than imported, for the same reason as the button classes. */}
      <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" className="size-4">
        <path d="M18 6 6 18M6 6l12 12" />
      </svg>
    </Button>
  );
}

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
  /** alertdialog for a confirm that interrupts, such as a delete. */
  role?: 'dialog' | 'alertdialog';
}

/**
 * A modal on the panel's stack: the top one traps focus and closes on Escape unless locked, focus
 * returns to the element that opened it, shortcuts pause and the page stops scrolling while any is open.
 */
export function Modal({ labelledBy, onClose, children, className, locked = false, role = 'dialog' }: ModalProps) {
  const id = useId();
  const panel = useRef<HTMLDivElement>(null);
  const close = useRef(onClose);
  const held = useRef(locked);
  const [asking, setAsking] = useState<((confirmed: boolean) => void) | null>(null);
  const answer = useRef(asking);

  // A layout effect, so an Escape pressed the moment the password form shows already finds the
  // check to cancel rather than closing the modal.
  useLayoutEffect(() => {
    // Back from a password check, the focus returns into the modal's own content.
    if (answer.current !== null && asking === null) {
      panel.current?.querySelector<HTMLElement>(focusable)?.focus();
    }

    answer.current = asking;
  }, [asking]);

  useLayoutEffect(() => {
    close.current = onClose;
    held.current = locked;
  }, [onClose, locked]);

  useEffect(() => {
    if (stack.length >= MAX_MODALS) {
      close.current();

      return undefined;
    }

    const opener = openerOf(document.activeElement);
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
    // A dialog may name the control that takes the focus, such as the safe choice that is not first.
    (panel.current?.querySelector<HTMLElement>('[data-autofocus]') ?? panel.current?.querySelector<HTMLElement>(focusable))?.focus();

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
        } else if (!panel.current.contains(document.activeElement)) {
          // The focus fell out, such as with a control that remounted: Tab brings it back in.
          event.preventDefault();
          (event.shiftKey ? last : first).focus();
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
    <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-[15vh] max-md:items-end max-md:p-0">
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
        role={role}
        data-lathe-modal
        aria-modal="true"
        aria-labelledby={asking === null ? labelledBy : `${id}-password`}
        className={cn('cn-dialog-content', className)}
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
