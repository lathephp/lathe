import { useTranslation } from '@lathephp/core';
import { CircleHelp } from 'lucide-react';
import { useId, useState } from 'react';

/** A help icon whose text shows on hover and focus, `width` wide (a number is pixels). */
export function HelpTooltip({ text, width }: { text: string; width: number | string }) {
  const { __ } = useTranslation();
  const id = useId();
  const [open, setOpen] = useState(false);
  const show = () => {
    setOpen(true);
  };
  const hide = () => {
    setOpen(false);
  };

  return (
    <span className="relative inline-flex">
      <button
        type="button"
        aria-label={__('Help')}
        aria-describedby={id}
        className="rounded text-muted-foreground hover:text-foreground"
        onMouseEnter={show}
        onMouseLeave={hide}
        onFocus={show}
        onBlur={hide}
        onKeyDown={(event) => {
          if (event.key === 'Escape') {
            hide();
          }
        }}
      >
        <CircleHelp aria-hidden="true" className="size-3.5" />
      </button>
      <span
        role="tooltip"
        id={id}
        hidden={!open}
        style={{ width: typeof width === 'number' ? `${String(width)}px` : width }}
        className="absolute start-0 top-full z-10 mt-1 rounded-md border border-border bg-background p-2 text-xs font-normal whitespace-pre-line text-foreground shadow-md"
      >
        {text}
      </span>
    </span>
  );
}
