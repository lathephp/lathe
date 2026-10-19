import { usePage } from '@inertiajs/react';
import { request, useLathe, useTranslation, type SafeHtml as SafeHtmlValue } from '@lathephp/core';
import { useRef, useState } from 'react';
import { SafeHtml } from '../../components/SafeHtml';
import { FilePicker } from '../../components/ui/FilePicker';
import { stringValue, type FormFieldProps } from '../registry';
import type { FormProps } from '../types';

type Tab = 'write' | 'preview';

const tabClass =
  'inline-flex h-7 items-center rounded-[6px] px-2.5 text-[13px] font-medium text-muted-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring aria-selected:bg-card aria-selected:text-foreground aria-selected:shadow-xs';

export interface MarkdownEditorProps extends FormFieldProps {
  /** Uploads a chosen image and returns its URL. Without it, the editor has no image button. */
  uploadImage?: (file: File) => Promise<string>;
}

/** A Markdown textarea with a Preview tab the server renders, so the preview matches the detail page. */
export default function MarkdownEditor({ field, value, onChange, id, describedBy, invalid, disabled, uploadImage }: MarkdownEditorProps) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const { resource, mode, id: recordId } = usePage<Pick<FormProps, 'resource' | 'mode' | 'id'>>().props;
  const [tab, setTab] = useState<Tab>('write');
  const [preview, setPreview] = useState<SafeHtmlValue | null>(null);
  const [failed, setFailed] = useState(false);
  const textarea = useRef<HTMLTextAreaElement>(null);
  const source = stringValue(value);
  const base = `${apiPath}/resources/${resource.uriKey}`;
  const attribute = encodeURIComponent(field.attribute);
  const url = mode === 'update' ? `${base}/${String(recordId)}/field/${attribute}/preview` : `${base}/field/${attribute}/preview`;

  async function showPreview() {
    setTab('preview');
    setPreview(null);
    setFailed(false);

    try {
      const result = await request<{ preview: SafeHtmlValue }>(url, { method: 'POST', body: { value: source } });
      setPreview(result?.preview ?? null);
    } catch {
      setFailed(true);
    }
  }

  /** Upload the image, then put it in the source where the cursor was. */
  async function addImage(file: File, upload: (file: File) => Promise<string>) {
    try {
      const url = await upload(file);
      const current = textarea.current?.value ?? source;
      const at = textarea.current?.selectionEnd ?? current.length;
      onChange(`${current.slice(0, at)}![${file.name.replace(/[[\]]/g, '')}](${url})${current.slice(at)}`);
    } catch {
      // The uploader has already shown why.
    }
  }

  // One box: a muted toolbar row with the tabs and the image button, then the textarea or the preview.
  return (
    <div className="overflow-hidden rounded-md border border-input bg-card has-aria-[invalid]:border-destructive">
      <div className="flex h-[38px] items-center gap-0.5 border-b border-border bg-muted px-1.5">
        <div role="tablist" className="flex gap-0.5">
          <button
            type="button"
            role="tab"
            id={`${id}-write`}
            aria-selected={tab === 'write'}
            className={tabClass}
            onClick={() => {
              setTab('write');
            }}
          >
            {__('Write')}
          </button>
          <button
            type="button"
            role="tab"
            id={`${id}-preview`}
            aria-selected={tab === 'preview'}
            className={tabClass}
            onClick={() => {
              void showPreview();
            }}
          >
            {__('Preview')}
          </button>
        </div>
        {uploadImage !== undefined && (
          <FilePicker
            variant="ghost"
            size="sm"
            accept="image/png,image/jpeg,image/gif,image/webp"
            disabled={disabled}
            onPick={(file) => {
              void addImage(file, uploadImage);
            }}
            className="ms-auto text-muted-foreground hover:text-foreground"
          >
            {__('Upload image')}
          </FilePicker>
        )}
      </div>
      <textarea
        ref={textarea}
        id={id}
        name={field.attribute}
        hidden={tab !== 'write'}
        rows={10}
        value={source}
        placeholder={field.placeholder ?? undefined}
        aria-describedby={describedBy}
        aria-invalid={invalid || undefined}
        aria-required={field.required === true || undefined}
        disabled={disabled}
        onChange={(event) => {
          onChange(event.target.value);
        }}
        className="block w-full resize-y bg-card px-3 py-2.5 font-mono text-[13px] leading-5 outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset disabled:opacity-60"
      />
      {tab === 'preview' && (
        <div role="tabpanel" aria-labelledby={`${id}-preview`} className="min-h-24 px-3 py-2.5">
          {failed ? (
            <p className="text-sm text-destructive">{__('The preview could not be loaded.')}</p>
          ) : preview === null ? (
            <p className="text-sm text-muted-foreground">{__('Loading preview…')}</p>
          ) : (
            <SafeHtml html={preview} className="prose max-w-none" />
          )}
        </div>
      )}
    </div>
  );
}
