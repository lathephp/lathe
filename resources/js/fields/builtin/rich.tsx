import { lazy, Suspense, type ComponentType } from 'react';
import { SafeHtml } from '../../components/SafeHtml';
import { DetailFallbackField, registerField, type DetailFieldProps, type FormFieldProps, type IndexFieldProps } from '../registry';
import CodeDetail from '../rich/CodeDetail';
import KeyValueForm, { KeyValueDetail } from '../key-value/KeyValueFields';
import SlugInput from '../slug/SlugInput';
import { useEditorUploads } from '../rich/useEditorUploads';
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

type ImageEditor = ComponentType<FormFieldProps & { uploadImage?: (file: File) => Promise<string> }>;

/** An editor of a field with files: it uploads images, and an upload the server refuses says why under it. */
function EditorWithFiles({ editor: Editor, ...props }: FormFieldProps & { editor: ImageEditor }) {
  const { uploadImage, error } = useEditorUploads(props.field);

  return (
    <>
      <Editor {...props} uploadImage={uploadImage} />
      {error !== null && <p className="mt-1 text-sm text-destructive">{error}</p>}
    </>
  );
}

function MarkdownForm(props: FormFieldProps) {
  return (
    <Suspense fallback={null}>
      {props.field.withFiles === true ? <EditorWithFiles {...props} editor={MarkdownEditor} /> : <MarkdownEditor {...props} />}
    </Suspense>
  );
}

function TiptapForm(props: FormFieldProps) {
  return (
    <Suspense fallback={null}>
      {props.field.withFiles === true ? <EditorWithFiles {...props} editor={TiptapEditor} /> : <TiptapEditor {...props} />}
    </Suspense>
  );
}

// The code editor loads only on a form that has a Code field.
const CodeEditor = lazy(() => import('../rich/CodeEditor'));

function CodeForm(props: FormFieldProps) {
  return (
    <Suspense fallback={null}>
      <CodeEditor {...props} />
    </Suspense>
  );
}

/** Register the renderers of the code, markdown, tiptap, slug and keyValue fields. */
export function registerRichFields(): void {
  registerField('code-field', { detail: CodeDetail, form: CodeForm });
  registerField('markdown-field', { detail: RichDetail, form: MarkdownForm });
  registerField('tiptap-field', { detail: RichDetail, form: TiptapForm });
  registerSlugAndKeyValueFields();
}

function SlugIndex({ cell }: IndexFieldProps) {
  return cell.display;
}

/** Register the slug and key-value renderers. */
function registerSlugAndKeyValueFields(): void {
  registerField('slug-field', { index: SlugIndex, form: SlugInput });
  registerField('key-value-field', { detail: KeyValueDetail, form: KeyValueForm });
}
