import { useTranslation } from '@lathephp/core';
import { Suspense } from 'react';
import { cn } from '../../lib/cn';
import type { FormFieldProps } from '../registry';
import { isBelongsToValue } from './BelongsToField';
import { BelongsToForm } from './lazy';
import { chip, tagList } from './TagField';
import type { AssociatableItem } from './useAssociatable';

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
