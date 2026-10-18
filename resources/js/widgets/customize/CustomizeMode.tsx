import { router } from '@inertiajs/react';
import { request, toast, useLathe, useTranslation } from '@lathephp/core';
import { type KeyboardEvent, type PointerEvent, useEffect, useId, useRef, useState, useSyncExternalStore } from 'react';
import { Button } from '../../components/ui/Button';
import type { LayoutDraft, WidgetDescriptor } from '../types';
import { addBack, hide, move, resize, sizeColumns, toPayload } from './draft';

/** Applies a change to the draft; Dashboard holds it, so the grid keeps its cards and their values. */
export type ChangeDraft = (update: (draft: LayoutDraft) => LayoutDraft) => void;

// The live region's text. Each card's handle writes it; the header holds the one region.
let said = '';
const listeners = new Set<() => void>();

function announce(text: string) {
  said = text;
  listeners.forEach((listener) => {
    listener();
  });
}

function subscribe(listener: () => void) {
  listeners.add(listener);

  return () => {
    listeners.delete(listener);
  };
}

const itemClass =
  'flex min-h-8 w-full items-center gap-2 rounded-md px-2 text-start text-sm outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring';

interface CustomizeHeaderProps {
  uriKey: string;
  draft: LayoutDraft;
  /** Leaves edit mode. */
  onDone: () => void;
}

/** The header in edit mode: the label, Reset to default, Cancel and Save layout, and the help line under them. */
export function CustomizeHeader({ uriKey, draft, onDone }: CustomizeHeaderProps) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const [busy, setBusy] = useState(false);
  const announcement = useSyncExternalStore(subscribe, () => said);
  const url = `${apiPath}/dashboards/${uriKey}/layout`;

  useEffect(
    () => () => {
      said = '';
    },
    [],
  );

  const send = async (method: 'PUT' | 'DELETE', failure: string) => {
    setBusy(true);

    try {
      await request(url, method === 'PUT' ? { method, body: { widgets: toPayload(draft) }, toastOnError: false } : { method, toastOnError: false });
    } catch {
      setBusy(false);
      toast.error(failure);

      return;
    }

    // Edit mode stays until the new props land, so the old order does not flash back.
    router.reload({ onFinish: onDone });
  };

  return (
    <>
      <span className="me-auto text-sm text-muted-foreground">{__('Editing layout')}</span>
      <div className="flex items-center gap-2">
        <Button variant="ghost" disabled={busy} onClick={() => void send('DELETE', __('The layout could not be reset.'))}>
          {__('Reset to default')}
        </Button>
        <Button variant="outline" disabled={busy} onClick={onDone}>
          {__('Cancel')}
        </Button>
        <Button disabled={busy} onClick={() => void send('PUT', __('The layout could not be saved.'))}>
          {__('Save layout')}
        </Button>
      </div>
      <p className="flex basis-full items-center gap-2.5 rounded-lg bg-muted px-3.5 py-2.5 text-sm text-muted-foreground">
        <svg aria-hidden="true" viewBox="0 0 24 24" className="size-4 shrink-0 fill-none stroke-current stroke-[1.75]">
          <circle cx="12" cy="12" r="10" />
          <path d="M12 16v-4M12 8h.01" strokeLinecap="round" />
        </svg>
        <span>
          {__(
            'Drag a card by its handle to move it. Change its size or hide it from its ⋯ menu. With a keyboard, focus the handle, press Space, then use the arrow keys. The layout is saved for your account.',
          )}
        </span>
      </p>
      <span aria-live="polite" className="sr-only">
        {announcement}
      </span>
    </>
  );
}

interface EditCardHeaderProps {
  widget: WidgetDescriptor;
  draft: LayoutDraft;
  change: ChangeDraft;
}

const steps: Record<string, number> = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 };

/** A card's header in edit mode: the drag handle, the title and the size menu. */
export function EditCardHeader({ widget, draft, change }: EditCardHeaderProps) {
  const { __ } = useTranslation();
  const name = widget.name;
  const index = draft.shown.findIndex((other) => other.uriKey === widget.uriKey);
  const count = draft.shown.length;
  // The order when the card was picked up, for Escape.
  const start = useRef<WidgetDescriptor[]>([]);
  const [picked, setPicked] = useState(false);
  const [dragging, setDragging] = useState(false);
  const stopDrag = useRef<(() => void) | null>(null);

  useEffect(
    () => () => {
      stopDrag.current?.();
    },
    [],
  );

  const onKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
    if (event.key === ' ') {
      event.preventDefault();

      if (picked) {
        setPicked(false);
        announce(__(':name dropped.', { name }));
      } else {
        start.current = draft.shown;
        setPicked(true);
        announce(__('Picked up :name. Use the arrow keys to move it, then Space to drop it.', { name }));
      }

      return;
    }

    if (!picked) {
      return;
    }

    const button = event.currentTarget;
    let to = index + (steps[event.key] ?? 0);

    if (event.key === 'Escape') {
      setPicked(false);
      to = start.current.findIndex((other) => other.uriKey === widget.uriKey);
      const before = start.current;
      change((current) => ({ ...current, shown: before }));
    } else if (to === index || to < 0 || to >= count) {
      return;
    } else {
      change((current) => ({ ...current, shown: move(current.shown, index, to) }));
    }

    event.preventDefault();
    announce(__(':name moved to place :n of :count.', { name, n: to + 1, count }));
    // React moves the card's node, which drops the handle's focus.
    requestAnimationFrame(() => {
      button.focus();
    });
  };

  const onPointerDown = (event: PointerEvent<HTMLButtonElement>) => {
    if (event.button !== 0) {
      return;
    }

    event.currentTarget.setPointerCapture(event.pointerId);
    const before = draft.shown;
    const escape = (key: globalThis.KeyboardEvent) => {
      if (key.key === 'Escape') {
        change((current) => ({ ...current, shown: before }));
        stopDrag.current?.();
      }
    };

    document.addEventListener('keydown', escape);
    stopDrag.current = () => {
      document.removeEventListener('keydown', escape);
      stopDrag.current = null;
      setDragging(false);
    };
    setDragging(true);
  };

  // The card under the pointer gives the dragged card its new place, live.
  const onPointerMove = (event: PointerEvent<HTMLButtonElement>) => {
    if (stopDrag.current === null) {
      return;
    }

    const over = document.elementFromPoint(event.clientX, event.clientY)?.closest<HTMLElement>('[data-lathe-widget]')?.dataset.latheWidget;

    if (over === undefined || over === widget.uriKey) {
      return;
    }

    change((current) => {
      const to = current.shown.findIndex((other) => other.uriKey === over);

      return to === -1 ? current : { ...current, shown: move(current.shown, current.shown.findIndex((other) => other.uriKey === widget.uriKey), to) };
    });
  };

  return (
    <div className="flex items-center gap-1.5">
      <button
        type="button"
        aria-label={__('Move :name', { name })}
        className={[
          '-ms-1.5 grid size-8 shrink-0 touch-none place-items-center rounded-md text-muted-foreground outline-none hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring',
          dragging ? 'cursor-grabbing' : 'cursor-grab',
          picked ? 'bg-muted text-foreground ring-2 ring-ring' : '',
        ].join(' ')}
        onKeyDown={onKeyDown}
        onPointerDown={onPointerDown}
        onPointerMove={onPointerMove}
        onPointerUp={() => stopDrag.current?.()}
        onPointerCancel={() => stopDrag.current?.()}
      >
        <svg aria-hidden="true" viewBox="0 0 12 16" className="h-4 w-3 fill-current">
          <circle cx="3" cy="3" r="1.4" />
          <circle cx="9" cy="3" r="1.4" />
          <circle cx="3" cy="8" r="1.4" />
          <circle cx="9" cy="8" r="1.4" />
          <circle cx="3" cy="13" r="1.4" />
          <circle cx="9" cy="13" r="1.4" />
        </svg>
      </button>
      <h2 className="min-w-0 flex-1 truncate text-sm font-medium">{name}</h2>
      <SizeMenu widget={widget} change={change} />
    </div>
  );
}

/** The edit ⋯ menu: the five sizes, then Hide from this dashboard. */
function SizeMenu({ widget, change }: { widget: WidgetDescriptor; change: ChangeDraft }) {
  const { __ } = useTranslation();
  const [open, setOpen] = useState(false);
  const menuId = useId();
  const root = useRef<HTMLDivElement>(null);
  const sizes = [
    ['1/4', __('Small')],
    ['1/3', __('Medium')],
    ['1/2', __('Large')],
    ['2/3', __('Wide')],
    ['full', __('Full')],
  ] as const;

  // A press anywhere outside the menu closes it.
  useEffect(() => {
    if (!open) {
      return;
    }

    const close = (event: globalThis.PointerEvent) => {
      if (!(event.target instanceof Node) || root.current?.contains(event.target) !== true) {
        setOpen(false);
      }
    };

    document.addEventListener('pointerdown', close);

    return () => {
      document.removeEventListener('pointerdown', close);
    };
  }, [open]);

  return (
    <div ref={root} className="relative -me-1.5 shrink-0">
      <button
        type="button"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={open ? menuId : undefined}
        aria-label={__('Menu for :title', { title: widget.name })}
        className="grid size-8 place-items-center rounded-md text-muted-foreground outline-none hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
        onClick={() => {
          setOpen(!open);
        }}
      >
        <svg aria-hidden="true" viewBox="0 0 24 24" className="size-4 fill-current">
          <circle cx="5" cy="12" r="1.75" />
          <circle cx="12" cy="12" r="1.75" />
          <circle cx="19" cy="12" r="1.75" />
        </svg>
      </button>
      {open && (
        <div
          id={menuId}
          role="menu"
          tabIndex={-1}
          className="absolute end-0 top-full z-10 mt-1 w-56 rounded-lg border border-border bg-background p-1 shadow-lg"
          onKeyDown={(event) => {
            if (event.key === 'Escape') {
              setOpen(false);
            }
          }}
        >
          {sizes.map(([width, label]) => (
            <button
              key={width}
              type="button"
              role="menuitemradio"
              aria-checked={widget.width === width}
              className={itemClass}
              onClick={() => {
                setOpen(false);
                change((current) => resize(current, widget.uriKey, width));
              }}
            >
              <span className="grid w-4 shrink-0 place-items-center">
                {widget.width === width && (
                  <svg aria-hidden="true" viewBox="0 0 24 24" className="size-3.5 fill-none stroke-current stroke-[2.25]">
                    <path d="M20 6 9 17l-5-5" strokeLinecap="round" strokeLinejoin="round" />
                  </svg>
                )}
              </span>
              {label}
              <span aria-hidden="true" className="ms-auto text-xs text-muted-foreground">
                {__(':n of :total', { n: sizeColumns[width] ?? 12, total: 12 })}
              </span>
            </button>
          ))}
          <div role="separator" className="my-1 h-px bg-border" />
          <button
            type="button"
            role="menuitem"
            className={itemClass}
            onClick={() => {
              setOpen(false);
              change((current) => hide(current, widget.uriKey));
            }}
          >
            {__('Hide from this dashboard')}
          </button>
        </div>
      )}
    </div>
  );
}

interface AddPanelProps {
  /** The dashboard's name. */
  name: string;
  draft: LayoutDraft;
  change: ChangeDraft;
}

/** The hidden cards, each with Add, which shows it at the end of the page. */
export function AddPanel({ name, draft, change }: AddPanelProps) {
  const { __ } = useTranslation();
  const headingId = useId();

  return (
    <section aria-labelledby={headingId} className="rounded-lg border border-border bg-background p-4 shadow-xs xl:w-72 xl:shrink-0">
      <h2 id={headingId} className="text-base font-semibold">
        {__('Add a widget')}
      </h2>
      <p className="mt-1 mb-3 text-xs text-muted-foreground">{__('Widgets on :name that are hidden. A new card goes to the end of the page.', { name })}</p>
      {draft.hidden.length === 0 ? (
        <p className="text-sm text-muted-foreground">{__('No hidden widgets.')}</p>
      ) : (
        <ul>
          {draft.hidden.map((widget) => (
            <li key={widget.uriKey} className="flex items-center gap-2.5 border-t border-border py-2.5">
              <span className="min-w-0 flex-1 truncate text-sm font-medium">{widget.name}</span>
              <Button
                variant="outline"
                size="sm"
                aria-label={__('Add :name', { name: widget.name })}
                onClick={() => {
                  change((current) => addBack(current, widget.uriKey));
                }}
              >
                {__('Add')}
              </Button>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
