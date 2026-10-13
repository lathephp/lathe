import { usePage } from '@inertiajs/react';
import { request, useLathe, useTranslation, type SafeHtml as SafeHtmlValue } from '@lathephp/core';
import { useState } from 'react';
import { SafeHtml } from '../../components/SafeHtml';
import { stringValue, type FormFieldProps } from '../registry';
import type { FormProps } from '../types';

type Tab = 'write' | 'preview';

const tabClass =
  'rounded-md px-3 py-1 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-ring aria-selected:bg-muted aria-selected:text-foreground text-muted-foreground';

/** A Markdown textarea with a Preview tab the server renders, so the preview matches the detail page. */
export default function MarkdownEditor({ field, value, onChange, id, describedBy, invalid, disabled }: FormFieldProps) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const { resource, mode, id: recordId } = usePage<Pick<FormProps, 'resource' | 'mode' | 'id'>>().props;
  const [tab, setTab] = useState<Tab>('write');
  const [preview, setPreview] = useState<SafeHtmlValue | null>(null);
  const [failed, setFailed] = useState(false);
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

  return (
    <div className="space-y-2">
      <div role="tablist" className="flex gap-1">
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
      <textarea
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
        className="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60 aria-[invalid]:border-destructive"
      />
      {tab === 'preview' && (
        <div role="tabpanel" aria-labelledby={`${id}-preview`} className="min-h-24 rounded-md border border-input px-3 py-2">
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
