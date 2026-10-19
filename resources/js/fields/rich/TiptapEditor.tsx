import { useTranslation } from '@lathephp/core';
import Image from '@tiptap/extension-image';
import { EditorContent, useEditor } from '@tiptap/react';
import type { EditorView } from '@tiptap/pm/view';
import StarterKit from '@tiptap/starter-kit';
import { Bold, Italic, List, Minus, type LucideIcon } from 'lucide-react';
import { stringValue, type FormFieldProps } from '../registry';

/**
 * Built once when the chunk loads, and every editor shares it, so no extension is
 * registered twice. StarterKit already holds Link: adding Link again would duplicate it.
 */
export const extensions = [StarterKit.configure({ link: { openOnClick: false } }), Image];

export interface TiptapEditorProps extends FormFieldProps {
  /** Uploads a pasted or dropped image and returns its URL. Without it, images are not uploaded. */
  uploadImage?: (file: File) => Promise<string>;
}

/** The images among pasted or dropped files. */
function imagesOf(files: FileList | undefined): File[] {
  return Array.from(files ?? []).filter((file) => file.type.startsWith('image/'));
}

/**
 * Upload each image and insert it at the selection once its URL is back. A failed upload
 * inserts nothing; the uploader has already shown why.
 */
async function insertImages(view: EditorView, files: File[], uploadImage: (file: File) => Promise<string>): Promise<void> {
  for (const file of files) {
    try {
      const src = await uploadImage(file);
      const node = view.state.schema.nodes.image?.create({ src, alt: file.name });

      if (node !== undefined) {
        view.dispatch(view.state.tr.replaceSelectionWith(node));
      }
    } catch {
      // Nothing to insert.
    }
  }
}

interface ToolProps {
  label: string;
  icon: LucideIcon;
  disabled: boolean;
  onClick: () => void;
}

function Tool({ label, icon: Icon, disabled, onClick }: ToolProps) {
  return (
    <button
      type="button"
      aria-label={label}
      title={label}
      disabled={disabled}
      className="inline-flex size-7 items-center justify-center rounded-[6px] text-muted-foreground outline-none hover:bg-card hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60"
      onClick={onClick}
    >
      <Icon aria-hidden="true" className="size-4" />
    </button>
  );
}

/** A rich-text editor whose value is HTML. The server sanitizes it on save and on render. */

/** The server's CSP nonce, read off the panel's own entry tag as app.tsx does. */
function cspNonce(): string | undefined {
  const nonce = document.querySelector<HTMLScriptElement>('script[type="module"][nonce]')?.nonce;

  return nonce === '' ? undefined : nonce;
}

export default function TiptapEditor({ field, value, onChange, id, describedBy, invalid, disabled, uploadImage }: TiptapEditorProps) {
  const { __ } = useTranslation();

  /** Take over a paste or drop that carries images, when this editor can upload them. */
  function upload(view: EditorView, images: File[]): boolean {
    if (uploadImage === undefined || images.length === 0) {
      return false;
    }

    void insertImages(view, images, uploadImage);

    return true;
  }

  const editor = useEditor({
    // Tiptap injects its base <style>; the strict policy lets it in only with the panel's nonce.
    injectNonce: cspNonce(),
    extensions,
    content: stringValue(value),
    editable: !disabled,
    editorProps: {
      handlePaste: (view, event) => upload(view, imagesOf(event.clipboardData?.files)),
      handleDrop: (view, event) => upload(view, imagesOf(event.dataTransfer?.files)),
      attributes: {
        id,
        role: 'textbox',
        'aria-multiline': 'true',
        'aria-label': field.label,
        ...(describedBy === undefined ? {} : { 'aria-describedby': describedBy }),
        ...(invalid ? { 'aria-invalid': 'true' } : {}),
        class:
          'prose max-w-none min-h-32 bg-card px-3.5 py-3 text-sm outline-none',
      },
    },
    onUpdate: ({ editor: current }) => {
      onChange(current.isEmpty ? '' : current.getHTML());
    },
  });

  return (
    // One box: a muted toolbar row over the text, as the rich text board draws it.
    <div className="overflow-hidden rounded-md border border-input bg-card focus-within:border-ring focus-within:ring-1 focus-within:ring-ring has-aria-[invalid]:border-destructive">
      <div role="toolbar" aria-label={__('Formatting')} className="flex h-[38px] items-center gap-0.5 border-b border-border bg-muted px-1.5">
        <Tool label={__('Bold')} icon={Bold} disabled={disabled} onClick={() => editor.chain().focus().toggleBold().run()} />
        <Tool label={__('Italic')} icon={Italic} disabled={disabled} onClick={() => editor.chain().focus().toggleItalic().run()} />
        <Tool label={__('Bulleted list')} icon={List} disabled={disabled} onClick={() => editor.chain().focus().toggleBulletList().run()} />
        <Tool label={__('Horizontal rule')} icon={Minus} disabled={disabled} onClick={() => editor.chain().focus().setHorizontalRule().run()} />
      </div>
      <EditorContent editor={editor} />
    </div>
  );
}
