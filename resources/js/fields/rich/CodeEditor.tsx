import { useLayoutEffect, useRef, type CSSProperties, type KeyboardEvent } from 'react';
import { stringValue, type FormFieldProps } from '../registry';

const DEFAULT_TAB_SIZE = 2;

function editorOptions(field: FormFieldProps['field']): Record<string, unknown> {
  const options = field.editorOptions;

  return typeof options === 'object' && options !== null ? (options as Record<string, unknown>) : {};
}

function boxHeight(field: FormFieldProps['field']): CSSProperties {
  if (field.fullHeight === true) return { height: '100%' };
  if (field.autoHeight === true) return {};
  if (typeof field.height === 'number') return { height: `${String(field.height)}px` };
  if (typeof field.height === 'string' && field.height !== '') return { height: field.height };

  return { height: '15rem' };
}

/** A monospace textarea with line numbers; Tab indents and Shift+Tab outdents instead of leaving the field. */
export default function CodeEditor({ field, value, onChange, id, describedBy, invalid, disabled }: FormFieldProps) {
  const source = stringValue(value);
  const options = editorOptions(field);
  const tabSize = typeof options.tabSize === 'number' && options.tabSize > 0 ? options.tabSize : DEFAULT_TAB_SIZE;
  const wrap = options.lineWrapping === true;
  const lines = source.split('\n').length;
  const area = useRef<HTMLTextAreaElement>(null);
  const gutter = useRef<HTMLDivElement>(null);
  // The caret after the next render: a controlled value resets it to the end.
  const caret = useRef<number | null>(null);

  useLayoutEffect(() => {
    if (caret.current !== null && area.current) {
      area.current.setSelectionRange(caret.current, caret.current);
      caret.current = null;
    }
  }, [source]);

  function edit(next: string, position: number) {
    caret.current = position;
    onChange(next);
  }

  function onKeyDown(event: KeyboardEvent<HTMLTextAreaElement>) {
    if (event.key !== 'Tab' || event.ctrlKey || event.metaKey || event.altKey) return;

    event.preventDefault();
    const { selectionStart: start, selectionEnd: end } = event.currentTarget;

    if (!event.shiftKey) {
      const pad = ' '.repeat(tabSize);
      edit(source.slice(0, start) + pad + source.slice(end), start + pad.length);

      return;
    }

    const lineStart = source.lastIndexOf('\n', start - 1) + 1;
    const indent = /^ */.exec(source.slice(lineStart))?.[0].length ?? 0;
    const removed = Math.min(indent, tabSize);

    if (removed > 0) {
      edit(source.slice(0, lineStart) + source.slice(lineStart + removed), Math.max(lineStart, start - removed));
    }
  }

  return (
    <div className="flex overflow-hidden rounded-md border border-input bg-background font-mono text-sm aria-[invalid]:border-destructive" aria-invalid={invalid || undefined} style={boxHeight(field)}>
      <div
        ref={gutter}
        data-testid="code-lines"
        aria-hidden="true"
        className="select-none overflow-hidden border-r border-input bg-muted px-2 py-2 text-end leading-6 text-muted-foreground"
      >
        {Array.from({ length: lines }, (_, index) => (
          <div key={index}>{index + 1}</div>
        ))}
      </div>
      <textarea
        ref={area}
        id={id}
        name={field.attribute}
        rows={field.autoHeight === true ? lines : undefined}
        wrap={wrap ? 'soft' : 'off'}
        spellCheck={false}
        value={source}
        placeholder={field.placeholder ?? undefined}
        aria-describedby={describedBy}
        aria-invalid={invalid || undefined}
        aria-required={field.required === true || undefined}
        disabled={disabled}
        style={{ tabSize, ...(field.autoHeight === true ? { overflow: 'hidden', resize: 'none' } : {}) }}
        onChange={(event) => {
          onChange(event.target.value);
        }}
        onKeyDown={onKeyDown}
        onScroll={(event) => {
          if (gutter.current) gutter.current.scrollTop = event.currentTarget.scrollTop;
        }}
        className="min-w-0 flex-1 resize-none bg-transparent px-3 py-2 leading-6 outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring disabled:opacity-60"
      />
    </div>
  );
}
