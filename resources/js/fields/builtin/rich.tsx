import { lazy, Suspense } from 'react';
import { SafeHtml } from '../../components/SafeHtml';
import { DetailFallbackField, registerField, type DetailFieldProps, type FormFieldProps } from '../registry';
import { Collapsible, lineCount } from '../text/Collapsible';
import { isSafeHtml } from '../text/TextValue';

// A separate chunk: the editor loads only on a form that has a Markdown field.
const MarkdownEditor = lazy(() => import('../rich/MarkdownEditor'));

/** The server rendered and sanitized the Markdown; display is its text, which sets the collapse. */
function MarkdownDetail({ field }: DetailFieldProps) {
  return isSafeHtml(field.value) ? (
    <Collapsible lines={lineCount(field.display)}>
      <SafeHtml html={field.value} className="prose max-w-none" />
    </Collapsible>
  ) : (
    <DetailFallbackField field={field} />
  );
}

function MarkdownForm(props: FormFieldProps) {
  return (
    <Suspense fallback={null}>
      <MarkdownEditor {...props} />
    </Suspense>
  );
}

/** Register the renderers of the code, markdown, tiptap, slug and keyValue fields. */
export function registerRichFields(): void {
  registerField('markdown-field', { detail: MarkdownDetail, form: MarkdownForm });
}
