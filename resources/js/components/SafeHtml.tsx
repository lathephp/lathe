import type { SafeHtml as SafeHtmlValue } from '@lathephp/core';

interface SafeHtmlProps {
  html: SafeHtmlValue;
  className?: string;
}

/** The one place the panel puts HTML on a page. The server sanitizes the value. */
export function SafeHtml({ html, className }: SafeHtmlProps) {
  return <div className={className} dangerouslySetInnerHTML={html} />;
}
