import { __panel, pauseShortcuts, resumeShortcuts, useTranslation } from '@lathephp/core';
import { Suspense, useEffect, useId, useLayoutEffect, useRef, useState, type ReactNode } from 'react';
import { cn } from '../lib/cn';
import { lazyNamed } from '../lib/lazy';

// A request that needs the password asks inside the top modal, so two dialogs never stack.
const ConfirmPasswordForm = lazyNamed(() => import('./ConfirmPasswordDialog').then((module) => module.ConfirmPasswordForm));

/** The open modals, bottom first. Only the top one takes keys. */
const stack: string[] = [];

/** At most this many modals open at once; a further one closes as it opens. */
export const MAX_MODALS = 3;

const focusable = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/** The dialog surface: popover ground, 16 px radius, the large shadow. */
export const modalSurface = 'relative w-full rounded-2xl border border-border bg-popover text-popover-foreground shadow-lg';

/**
 * The surface with its spacing: 20 px between parts and 6 px from the title to its description,
 * which sit in the panel or in its form (a display: contents form). A flex column, so an absolute
 * close button takes no gap. On a phone it is a bottom sheet with a grabber, a 17 px title and a
 * 15 px description.
 */
export const modalPanel = `${modalSurface} flex flex-col gap-5 px-6 py-5 [&_h2]:leading-[22px] [&>h2:has(+p)]:-mb-3.5 [&>form>h2:has(+p)]:-mb-3.5 max-md:px-4 max-md:pt-2 max-md:pb-6 max-md:before:mx-auto max-md:before:-mb-1 max-md:before:h-1 max-md:before:w-9 max-md:before:rounded-full max-md:before:bg-border max-md:before:content-[''] max-md:[&_h2]:text-[17px] max-md:[&_h2]:leading-6 max-md:[&_h2+p]:text-[15px] max-md:[&_h2+p]:leading-[22px]`;

/** The buttons, right-aligned with the primary last; on a phone they stack 44 px tall with the primary on top. */
export const modalFooter = 'flex justify-end gap-2 max-md:flex-col-reverse max-md:[&>*]:h-11 max-md:[&>*]:rounded-[10px] max-md:[&>*]:text-base';

/** A text input in a dialog: 32 px, 8 px radius, a ring and a 3 px primary halo on focus. */
export const modalInput =
  'h-8 w-full rounded-lg border border-input bg-background px-2.5 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-primary-subtle aria-[invalid]:border-destructive';

/**
 * The panel Button's classes, for the dialogs that open from a first-load page: a component import
 * would add its chunk to every lazy import's preload list there.
 */
export const outlineButton = 'lathe-button lathe-button-outline lathe-button-size-default';
export const primaryButton = 'lathe-button lathe-button-default lathe-button-size-default';

/** The X at the top end. It comes after the content, so the focus still opens on the first field. */
export function ModalClose({ onClick }: { onClick: () => void }) {
  const { __ } = useTranslation();

  return (
    <button
      type="button"
      aria-label={__('Close')}
      className="absolute end-3.5 top-3.5 inline-flex size-[30px] items-center justify-center rounded-md text-muted-foreground outline-none hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-focus"
      onClick={onClick}
    >
      {/* Drawn here rather than imported, for the same reason as the button classes. */}
      <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" className="size-4">
        <path d="M18 6 6 18M6 6l12 12" />
      </svg>
    </button>
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
        // On a phone every modal is a bottom sheet.
        className={cn(className ?? `${modalPanel} max-w-[400px]`, 'max-md:max-w-none max-md:rounded-t-2xl max-md:rounded-b-none max-md:border-x-0 max-md:border-b-0')}
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
