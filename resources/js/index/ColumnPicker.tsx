import { router } from '@inertiajs/react';
import { request, toast, useLathe, useTranslation } from '@lathephp/core';
import { GripVertical, Pin } from 'lucide-react';
import { useEffect, useRef, useState, type DragEvent, type KeyboardEvent } from 'react';
import { cn } from '../lib/cn';
import type { ColumnChoice } from './types';

interface ColumnPickerProps {
  uriKey: string;
  /** Every index column in code order. */
  columns: { key: string; label: string }[];
  /** The user's saved choice, null for the code's columns. */
  choice: ColumnChoice | null;
  /** The Columns button: a press on it toggles the picker, and Escape returns focus to it. */
  trigger: HTMLElement | null;
  onClose: () => void;
}

const iconButton =
  'grid size-6 shrink-0 place-items-center rounded text-muted-foreground outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring';

// The saves still to reach the server, one after another.
let queue: Promise<void> = Promise.resolve();

/** Sends once every save before it has settled. */
function inTurn<T>(send: () => Promise<T>): Promise<T> {
  const sent = queue.then(send);
  queue = sent.then(
    () => undefined,
    () => undefined,
  );

  return sent;
}

/**
 * The index's columns: show, hide, reorder and pin each, saved for the signed-in user at once.
 * The first code column is the row's title, so it always shows, stays first and stays pinned.
 */
export function ColumnPicker({ uriKey, columns, choice, trigger, onClose }: ColumnPickerProps) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const defaults: ColumnChoice = { order: columns.map((column) => column.key), hidden: [], pinned: [] };
  const [current, setCurrent] = useState<ColumnChoice>(choice ?? defaults);
  const [dragging, setDragging] = useState<string | null>(null);
  const [announcement, setAnnouncement] = useState('');
  const box = useRef<HTMLDivElement>(null);
  const url = `${apiPath}/resources/${uriKey}/columns`;
  const title = columns[0]?.key;
  // The server appends a code column the saved order lacks, so every key here has a column.
  const rows = current.order.flatMap((key) => columns.filter((column) => column.key === key));

  useEffect(() => {
    box.current?.focus();
  }, []);

  // A press outside closes the picker, and Escape closes it and returns focus to the button.
  useEffect(() => {
    function outside(event: PointerEvent) {
      if (event.target instanceof Node && box.current?.contains(event.target) === false && trigger?.contains(event.target) !== true) {
        onClose();
      }
    }

    function escape(event: globalThis.KeyboardEvent) {
      if (event.key === 'Escape') {
        onClose();
        trigger?.focus();
      }
    }

    document.addEventListener('pointerdown', outside);
    document.addEventListener('keydown', escape);

    return () => {
      document.removeEventListener('pointerdown', outside);
      document.removeEventListener('keydown', escape);
    };
  }, [trigger, onClose]);

  // The picker shows the change at once; a failed save puts the old choice back. Each save waits
  // for the one before, so two quick changes reach the server in the order they were made.
  async function save(next: ColumnChoice, method: 'PUT' | 'DELETE' = 'PUT') {
    const before = current;
    setCurrent(next);
    try {
      await inTurn(() => request(url, method === 'PUT' ? { method, body: next, toastOnError: false } : { method, toastOnError: false }));
    } catch {
      setCurrent(before);
      toast.error(__('Something went wrong. Please try again.'));

      return;
    }

    router.reload({ only: ['table'] });
  }

  function toggle(list: 'hidden' | 'pinned', key: string) {
    const keys = current[list];

    void save({ ...current, [list]: keys.includes(key) ? keys.filter((other) => other !== key) : [...keys, key] });
  }

  function move(key: string, to: number) {
    const order = current.order.filter((other) => other !== key);
    order.splice(to, 0, key);
    setAnnouncement(__('Moved :name to position :position', { name: columns.find((column) => column.key === key)?.label ?? key, position: to + 1 }));
    void save({ ...current, order });
  }

  function onGripKeyDown(event: KeyboardEvent<HTMLButtonElement>, key: string, index: number) {
    const step = event.key === 'ArrowUp' ? -1 : event.key === 'ArrowDown' ? 1 : 0;

    if (!event.altKey || step === 0) {
      return;
    }

    event.preventDefault();

    // Nothing moves above the title column.
    if (index + step >= 1 && index + step < rows.length) {
      const button = event.currentTarget;
      move(key, index + step);
      // React moves the button's node, which drops its focus.
      requestAnimationFrame(() => {
        button.focus();
      });
    }
  }

  function onDrop(event: DragEvent, index: number) {
    event.preventDefault();

    if (dragging !== null) {
      move(dragging, Math.max(index, 1));
    }

    setDragging(null);
  }

  return (
    <div
      ref={box}
      role="dialog"
      aria-label={__('Columns')}
      tabIndex={-1}
      className="absolute end-0 top-full z-20 mt-1 w-64 overflow-hidden rounded-xl border border-border bg-popover text-start text-sm font-normal text-popover-foreground shadow-md outline-none"
    >
      <div className="flex h-10 items-center justify-between border-b border-border px-3">
        <span className="font-semibold">{__('Columns')}</span>
        <span className="text-xs text-muted-foreground">{__('Drag to reorder')}</span>
      </div>
      <ul className="p-1">
        {rows.map((column, index) => {
          const locked = column.key === title;
          const pinned = current.pinned.includes(column.key);

          return (
            <li
              key={column.key}
              className={cn('flex h-8 items-center gap-2 rounded-md px-1.5 hover:bg-muted', dragging === column.key && 'opacity-50')}
              onDragOver={(event) => {
                if (dragging !== null) {
                  event.preventDefault();
                }
              }}
              onDrop={(event) => {
                onDrop(event, index);
              }}
            >
              {locked ? (
                <span className="size-6 shrink-0" />
              ) : (
                <button
                  type="button"
                  draggable
                  aria-label={__('Reorder :name', { name: column.label })}
                  className={cn(iconButton, 'cursor-grab')}
                  onDragStart={(event) => {
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', column.key);
                    setDragging(column.key);
                  }}
                  onDragEnd={() => {
                    setDragging(null);
                  }}
                  onKeyDown={(event) => {
                    onGripKeyDown(event, column.key, index);
                  }}
                >
                  <GripVertical aria-hidden="true" className="size-3.5" />
                </button>
              )}
              <label className="flex min-w-0 flex-1 items-center gap-2">
                <input
                  type="checkbox"
                  className="size-4 shrink-0"
                  checked={!current.hidden.includes(column.key)}
                  disabled={locked}
                  onChange={() => {
                    toggle('hidden', column.key);
                  }}
                />
                <span className="truncate">{column.label}</span>
              </label>
              {locked ? (
                <span className="text-xs text-muted-foreground">{__('Pinned')}</span>
              ) : (
                <button
                  type="button"
                  aria-label={__('Pin :name', { name: column.label })}
                  aria-pressed={pinned}
                  className={cn(iconButton, pinned && 'text-foreground')}
                  onClick={() => {
                    toggle('pinned', column.key);
                  }}
                >
                  <Pin aria-hidden="true" className={cn('size-3.5', pinned && 'fill-current')} />
                </button>
              )}
            </li>
          );
        })}
      </ul>
      <div className="flex h-10 items-center border-t border-border px-3">
        <button
          type="button"
          className="rounded-sm font-medium text-link outline-none hover:text-link-hover focus-visible:ring-2 focus-visible:ring-ring"
          onClick={() => {
            void save(defaults, 'DELETE');
          }}
        >
          {__('Reset to default')}
        </button>
      </div>
      <p aria-live="polite" className="sr-only">
        {announcement}
      </p>
    </div>
  );
}
