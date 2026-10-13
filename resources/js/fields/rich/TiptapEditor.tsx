import { useTranslation } from '@lathephp/core';
import Image from '@tiptap/extension-image';
import { EditorContent, useEditor } from '@tiptap/react';
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
      className="rounded-md p-1.5 text-muted-foreground outline-none hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60"
      onClick={onClick}
    >
      <Icon aria-hidden="true" className="size-4" />
    </button>
  );
}

/** A rich-text editor whose value is HTML. The server sanitizes it on save and on render. */
export default function TiptapEditor({ field, value, onChange, id, describedBy, invalid, disabled }: TiptapEditorProps) {
  const { __ } = useTranslation();
  const editor = useEditor({
    extensions,
    content: stringValue(value),
    editable: !disabled,
    editorProps: {
      attributes: {
        id,
        role: 'textbox',
        'aria-multiline': 'true',
        'aria-label': field.label,
        ...(describedBy === undefined ? {} : { 'aria-describedby': describedBy }),
        ...(invalid ? { 'aria-invalid': 'true' } : {}),
        class:
          'prose max-w-none min-h-32 rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring aria-[invalid]:border-destructive',
      },
    },
    onUpdate: ({ editor: current }) => {
      onChange(current.isEmpty ? '' : current.getHTML());
    },
  });

  return (
    <div className="space-y-2">
      <div role="toolbar" aria-label={__('Formatting')} className="flex gap-1">
        <Tool label={__('Bold')} icon={Bold} disabled={disabled} onClick={() => editor.chain().focus().toggleBold().run()} />
        <Tool label={__('Italic')} icon={Italic} disabled={disabled} onClick={() => editor.chain().focus().toggleItalic().run()} />
        <Tool label={__('Bulleted list')} icon={List} disabled={disabled} onClick={() => editor.chain().focus().toggleBulletList().run()} />
        <Tool label={__('Horizontal rule')} icon={Minus} disabled={disabled} onClick={() => editor.chain().focus().setHorizontalRule().run()} />
      </div>
      <EditorContent editor={editor} />
    </div>
  );
}
