import type { SafeHtml as SafeHtmlValue } from '@lathephp/core';
import { SafeHtml } from '../../components/SafeHtml';
import type { FieldSchema } from '../types';
import { Collapsible, lineCount } from './Collapsible';
import { CopyButton } from './CopyButton';

/** Whether a wire value is the `{__html}` object the server sends for sanitized HTML. */
export function isSafeHtml(value: unknown): value is SafeHtmlValue {
  return typeof value === 'object' && value !== null && '__html' in value && typeof value.__html === 'string';
}

/** The strings of a payload list, such as suggestions or locales. */
export function stringList(value: unknown): string[] {
  return Array.isArray(value) ? value.filter((item): item is string => typeof item === 'string') : [];
}

interface TextValueProps {
  field: FieldSchema;
  value: unknown;
  display: string;
  /** Keep line breaks and collapse a long value: a textarea on the detail page. */
  multiline?: boolean;
}

/** A text value for index and detail: plain text, sanitized HTML, a copy button, a collapse. */
export function TextValue({ field, value, display, multiline = false }: TextValueProps) {
  const body =
    field.asHtml === true && isSafeHtml(value) ? (
      <SafeHtml html={value} />
    ) : (
      <span className={multiline ? 'whitespace-pre-wrap' : undefined}>{display}</span>
    );
  const content = multiline ? (
    <Collapsible lines={lineCount(display)} open={field.alwaysShow === true || field.expanded === true}>
      {body}
    </Collapsible>
  ) : (
    body
  );

  if (field.copyable !== true || display === '') {
    return content;
  }

  return (
    <span className="inline-flex items-start gap-1">
      {content}
      <CopyButton text={display} />
    </span>
  );
}
