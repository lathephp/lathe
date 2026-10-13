import { lazy, Suspense } from 'react';
import { SafeHtml } from '../../components/SafeHtml';
import { DetailFallbackField, registerField, type DetailFieldProps, type FormFieldProps, type IndexFieldProps } from '../registry';
import KeyValueForm, { KeyValueDetail } from '../key-value/KeyValueFields';
import SlugInput from '../slug/SlugInput';
import { Collapsible, lineCount } from '../text/Collapsible';
import { isSafeHtml } from '../text/TextValue';

// A separate chunk: the editor loads only on a form that has a Markdown field.
const MarkdownEditor = lazy(() => import('../rich/MarkdownEditor'));
// Tiptap and ProseMirror load only on a form that has a Tiptap field.
const TiptapEditor = lazy(() => import('../rich/TiptapEditor'));

/** The server rendered and sanitized the HTML; display is its text, which sets the collapse. */
function RichDetail({ field }: DetailFieldProps) {
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

function TiptapForm(props: FormFieldProps) {
  return (
    <Suspense fallback={null}>
      <TiptapEditor {...props} />
    </Suspense>
  );
}

/** Register the renderers of the code, markdown, tiptap, slug and keyValue fields. */
export function registerRichFields(): void {
  registerField('markdown-field', { detail: MarkdownDetail, form: MarkdownForm });
  registerSlugAndKeyValueFields();
}

function SlugIndex({ cell }: IndexFieldProps) {
  return cell.display;
}

/** Register the slug and key-value renderers. */
function registerSlugAndKeyValueFields(): void {
  registerField('slug-field', { index: SlugIndex, form: SlugInput });
  registerField('key-value-field', { detail: KeyValueDetail, form: KeyValueForm });
  registerField('markdown-field', { detail: RichDetail, form: MarkdownForm });
  registerField('tiptap-field', { detail: RichDetail, form: TiptapForm });
}
