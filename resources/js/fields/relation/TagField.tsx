import { Suspense, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '../../lib/cn';
import type { DetailFieldProps, IndexFieldProps } from '../registry';
import type { FieldSchema } from '../types';
import { isBelongsToValue } from './BelongsToField';
import { PreviewPanel } from './lazy';
import type { AssociatableItem } from './useAssociatable';

export const chip = 'rounded-full bg-muted px-2 py-0.5 text-xs font-medium';

export function tagList(value: unknown): AssociatableItem[] {
  return Array.isArray(value) ? value.filter(isBelongsToValue) : [];
}

/** The tags as chips: inline for displayAsGroup, stacked for displayAsList; withPreview opens a tag's preview panel on click. */
function TagChips({ field, value }: { field: FieldSchema; value: unknown }) {
  const [previewing, setPreviewing] = useState<string | number | null>(null);
  const list = useRef<HTMLUListElement>(null);
  const items = tagList(value);
  const preview = field.withPreview === true && typeof field.resource === 'string' ? field.resource : null;

  if (items.length === 0) {
    return <span className="text-muted-foreground">—</span>;
  }

  return (
    <>
      <ul ref={list} className={cn('flex gap-1', field.display === 'list' ? 'flex-col items-start' : 'flex-wrap')}>
        {items.map((item) => (
          <li key={item.value}>
            {preview === null ? (
              <span className={chip}>{item.display}</span>
            ) : (
              <button
                type="button"
                data-tag={item.value}
                className={cn(chip, 'hover:bg-accent')}
                onClick={() => {
                  setPreviewing(item.value);
                }}
              >
                {item.display}
              </button>
            )}
          </li>
        ))}
      </ul>
      {preview !== null &&
        previewing !== null &&
        // The panel lies over the page; the wrapper keeps its clicks from reaching an index row through React's tree.
        createPortal(
          // eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-static-element-interactions
          <div
            onClick={(event) => {
              event.stopPropagation();
            }}
          >
            <Suspense fallback={null}>
              <PreviewPanel
                resource={{ uriKey: preview }}
                id={String(previewing)}
                onClose={() => {
                  setPreviewing(null);
                  // The chip that opened the panel takes the focus back.
                  list.current?.querySelector<HTMLElement>(`[data-tag="${String(previewing)}"]`)?.focus();
                }}
              />
            </Suspense>
          </div>,
          document.body,
        )}
    </>
  );
}

export function TagIndex({ field, cell }: IndexFieldProps) {
  return <TagChips field={field} value={cell.value} />;
}

export function TagDetail({ field }: DetailFieldProps) {
  return <TagChips field={field} value={field.value} />;
}
