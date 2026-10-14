import { css } from '@codemirror/lang-css';
import { html } from '@codemirror/lang-html';
import { javascript } from '@codemirror/lang-javascript';
import { json } from '@codemirror/lang-json';
import { markdown } from '@codemirror/lang-markdown';
import { php } from '@codemirror/lang-php';
import { sql } from '@codemirror/lang-sql';
import { xml } from '@codemirror/lang-xml';
import { yaml } from '@codemirror/lang-yaml';
import CodeMirror, { EditorView, type Extension } from '@uiw/react-codemirror';
import { useMemo } from 'react';
import { cn } from './lib/utils';

const mono = 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace';

// A transparent surface, so the cn-textarea box shows through, with the gutter and
// selection in theme tokens for light and dark mode.
const codeTheme = EditorView.theme({
  '&': { backgroundColor: 'transparent', color: 'var(--foreground)' },
  '.cm-content': { fontFamily: mono, fontSize: '13px', padding: '12px 0' },
  '.cm-gutters': {
    backgroundColor: 'transparent',
    color: 'var(--muted-foreground)',
    border: 'none',
    paddingLeft: '8px',
  },
  '.cm-activeLine': { backgroundColor: 'color-mix(in oklab, var(--foreground) 5%, transparent)' },
  '.cm-activeLineGutter': { backgroundColor: 'transparent', color: 'var(--foreground)' },
  '.cm-selectionBackground, &.cm-focused .cm-selectionBackground': { backgroundColor: 'var(--accent)' },
  '&.cm-focused': { outline: 'none' },
  '.cm-cursor, .cm-dropCursor': { borderLeftColor: 'var(--foreground)' },
});

const languages: Record<string, (() => Extension) | undefined> = {
  javascript: () => javascript(),
  js: () => javascript(),
  typescript: () => javascript({ typescript: true }),
  ts: () => javascript({ typescript: true }),
  jsx: () => javascript({ jsx: true }),
  tsx: () => javascript({ jsx: true, typescript: true }),
  json: () => json(),
  html: () => html(),
  css: () => css(),
  php: () => php(),
  sql: () => sql(),
  xml: () => xml(),
  yaml: () => yaml(),
  yml: () => yaml(),
  markdown: () => markdown(),
  md: () => markdown(),
};

export interface CodeEditorProps {
  value: string;
  onChange?: (value: string) => void;
  /** A language name such as php, json or tsx; an unknown one edits plain text. */
  language?: string | null;
  height?: string | number;
  readOnly?: boolean;
  id?: string;
  className?: string;
  'aria-invalid'?: boolean;
  'aria-label'?: string;
}

/** A CodeMirror editor with line numbers and syntax highlighting. */
function CodeEditor({
  value,
  onChange,
  language,
  height = 300,
  readOnly = false,
  id,
  className,
  'aria-label': label,
  ...props
}: CodeEditorProps) {
  const extensions = useMemo(() => {
    const factory = language == null ? undefined : languages[language];
    // The editable element is CodeMirror's content node, so the name goes there.
    const named = [codeTheme, EditorView.contentAttributes.of(label === undefined ? {} : { 'aria-label': label })];

    return factory === undefined ? named : [...named, factory()];
  }, [language, label]);
  const size = typeof height === 'number' ? `${String(height)}px` : height;

  return (
    <div
      data-slot="code-editor"
      aria-invalid={props['aria-invalid']}
      className={cn('cn-textarea overflow-hidden p-0!', className)}
    >
      <CodeMirror
        id={id}
        value={value}
        onChange={onChange}
        theme="none"
        extensions={extensions}
        readOnly={readOnly}
        height={size === 'auto' ? undefined : size}
        basicSetup={{
          lineNumbers: true,
          foldGutter: !readOnly,
          highlightActiveLine: !readOnly,
          highlightActiveLineGutter: !readOnly,
          tabSize: 4,
        }}
        aria-invalid={props['aria-invalid']}
      />
    </div>
  );
}

export { CodeEditor };
