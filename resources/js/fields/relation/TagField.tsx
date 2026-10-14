import { useTranslation } from '@lathephp/core';
import { Suspense, useState } from 'react';
import { cn } from '../../lib/cn';
import type { DetailFieldProps, FormFieldProps, IndexFieldProps } from '../registry';
import type { FieldSchema } from '../types';
import { isBelongsToValue } from './BelongsToField';
import { BelongsToForm, PreviewModal } from './lazy';
import type { AssociatableItem } from './useAssociatable';

const chip = 'rounded-full bg-muted px-2 py-0.5 text-xs font-medium';

function tagList(value: unknown): AssociatableItem[] {
  return Array.isArray(value) ? value.filter(isBelongsToValue) : [];
}

/** The tags as chips: inline for displayAsGroup, stacked for displayAsList; withPreview opens a tag's preview on click. */
function TagChips({ field, value }: { field: FieldSchema; value: unknown }) {
  const [previewing, setPreviewing] = useState<string | number | null>(null);
  const items = tagList(value);
  const preview = field.withPreview === true && typeof field.resource === 'string' ? field.resource : null;

  if (items.length === 0) {
    return <span className="text-muted-foreground">—</span>;
  }

  return (
    <>
      <ul className={cn('flex gap-1', field.display === 'list' ? 'flex-col items-start' : 'flex-wrap')}>
        {items.map((item) => (
          <li key={item.value}>
            {preview === null ? (
              <span className={chip}>{item.display}</span>
            ) : (
              <button
                type="button"
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
      {preview !== null && previewing !== null && (
        // The modal renders inside the index row, so its clicks would bubble to the row and open the record.
        // eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-static-element-interactions
        <div
          onClick={(event) => {
            event.stopPropagation();
          }}
        >
          <Suspense fallback={null}>
            <PreviewModal
              uriKey={preview}
              id={previewing}
              onClose={() => {
                setPreviewing(null);
              }}
            />
          </Suspense>
        </div>
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

/** Toggles over the preloaded tags, or the chosen tags and the server-searched picker to add more. */
export function TagForm(props: FormFieldProps) {
  const { __ } = useTranslation();
  const { field, value, onChange, id, describedBy, disabled } = props;
  const chosen = tagList(value);
  const has = (item: AssociatableItem) => chosen.some((tag) => String(tag.value) === String(item.value));
  const without = (item: AssociatableItem) => chosen.filter((tag) => String(tag.value) !== String(item.value));

  if (field.preload === true && Array.isArray(field.options)) {
    return (
      <div id={id} role="group" aria-describedby={describedBy} className="flex flex-wrap gap-1">
        {tagList(field.options).map((item) => (
          <button
            key={item.value}
            type="button"
            aria-pressed={has(item)}
            disabled={disabled}
            onClick={() => {
              onChange(has(item) ? without(item) : [...chosen, item]);
            }}
            className={cn(chip, has(item) ? 'bg-primary text-primary-foreground' : 'hover:bg-accent')}
          >
            {item.display}
          </button>
        ))}
      </div>
    );
  }

  return (
    <div className="space-y-2">
      {chosen.length > 0 && (
        <ul className="flex flex-wrap gap-1">
          {chosen.map((item) => (
            <li key={item.value} className={cn(chip, 'inline-flex items-center gap-1')}>
              {item.display}
              <button
                type="button"
                aria-label={__('Remove :title', { title: item.display })}
                disabled={disabled}
                onClick={() => {
                  onChange(without(item));
                }}
              >
                ×
              </button>
            </li>
          ))}
        </ul>
      )}
      <Suspense fallback={<div className="h-9" aria-hidden="true" />}>
        <BelongsToForm
          {...props}
          // Tags not preloaded are too many to list, so they always use the searched picker.
          field={{ ...field, searchable: true }}
          value={null}
          onChange={(picked) => {
            if (isBelongsToValue(picked) && !has(picked)) {
              onChange([...chosen, { value: picked.value, display: picked.display }]);
            }
          }}
        />
      </Suspense>
    </div>
  );
}
