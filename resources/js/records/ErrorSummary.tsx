import { useTranslation } from '@lathephp/core';
import { forwardRef } from 'react';

/**
 * The circled exclamation mark of the summary and the field errors. It is drawn here, not imported
 * from lucide-react, because the index shares that icon: importing it would split it into a chunk
 * of its own that the index's first load then fetches.
 */
export function AlertIcon({ className }: { className: string }) {
  return (
    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <circle cx="12" cy="12" r="10" />
      <path d="M12 8v4M12 16h.01" />
    </svg>
  );
}
import type { FormFieldData } from '../fields/types';

/** Moves focus to a field's control, and brings it into view. */
export function goToField(id: string) {
  const control = document.getElementById(id);

  control?.focus({ preventScroll: true });
  control?.scrollIntoView({ block: 'center' });
}

/**
 * The top of a form after a failed save: how many fields need a change, and a link to each,
 * named by its label, with the message beside it. The lines follow the form's field order.
 */
export const ErrorSummary = forwardRef<HTMLDivElement, { errors: Record<string, string>; fields: FormFieldData[]; idPrefix: string }>(function ErrorSummary({ errors, fields, idPrefix }, ref) {
  const { __ } = useTranslation();
  const messages = new Map<string, string>();

  for (const [key, message] of Object.entries(errors)) {
    const attribute = key.split('.')[0] ?? key;

    if (!messages.has(attribute)) {
      messages.set(attribute, message);
    }
  }

  if (messages.size === 0) {
    return null;
  }

  const known = fields.filter((field) => messages.has(field.attribute)).map((field) => field.attribute);
  const attributes = [...known, ...[...messages.keys()].filter((attribute) => !known.includes(attribute))];
  const count = attributes.length;

  return (
    <div ref={ref} role="alert" className="flex scroll-mt-20 gap-2.5 rounded-lg border border-destructive bg-destructive-subtle px-4 py-3.5 text-destructive-subtle-foreground">
      <AlertIcon className="mt-px size-[18px] shrink-0" />
      <div className="flex flex-col gap-1">
        <p className="text-sm font-semibold">{count === 1 ? __('1 field needs a change before you can save') : __(':count fields need a change before you can save', { count })}</p>
        <ul className="list-disc ps-[18px] text-[13px] leading-5">
          {attributes.map((attribute) => {
            const id = `${idPrefix}-${attribute}`;

            return (
              <li key={attribute}>
                <a
                  href={`#${id}`}
                  className="text-destructive-subtle-foreground underline underline-offset-2"
                  onClick={(event) => {
                    event.preventDefault();
                    goToField(id);
                  }}
                >
                  {fields.find((field) => field.attribute === attribute)?.label ?? attribute}
                </a>
                : {messages.get(attribute)}
              </li>
            );
          })}
        </ul>
      </div>
    </div>
  );
});
