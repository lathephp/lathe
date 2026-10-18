import { request, toast, useLathe, useTranslation } from '@lathephp/core';
import { X } from 'lucide-react';
import { useEffect, useId, useState } from 'react';
import { createPortal } from 'react-dom';
import { Modal } from '../../components/Modal';
import { FormFields } from '../../records/FormFields';
import { useRecordForm } from '../../records/useRecordForm';
import type { FieldSchema, FormPayload } from '../types';
import { useAssociatableEndpoint, type AssociatableItem, type LookupRecord } from './useAssociatable';

interface CreateRelationSheetProps {
  field: FieldSchema;
  record?: LookupRecord;
  /** What was typed in the picker's search; it starts in the related title field. */
  text: string;
  /** The record that was just created, as the picker holds a chosen one. */
  onCreated: (item: { value: string | number; display: string }) => void;
  onClose: () => void;
}

/** The related Resource's create form in a sheet from the right edge; saving selects the new record in the field. */
export function CreateRelationSheet({ field, record, text, onCreated, onClose }: CreateRelationSheetProps) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const titleId = useId();
  const endpoint = useAssociatableEndpoint(field, record);
  const uriKey = String(field.resource);
  const label = typeof field.singularLabel === 'string' ? field.singularLabel : field.label;
  const [form, setForm] = useState<FormPayload | null>(null);

  useEffect(() => {
    const abort = new AbortController();

    request<FormPayload>(`${apiPath}/resources/${uriKey}/creation-fields`, { signal: abort.signal, toastOnError: false }).then(
      (payload) => {
        // The server names the related title field in createTitle.
        setForm(payload === null || text === '' ? payload : { ...payload, fields: payload.fields.map((item) => (item.attribute === field.createTitle ? { ...item, value: text } : item)) });
      },
      () => {
        if (!abort.signal.aborted) {
          toast.error(__('Something went wrong. Please try again.'));
          onClose();
        }
      },
    );

    return () => {
      abort.abort();
    };
    // The form loads once, when the sheet opens.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  /** The new record's picker value comes from the same route the picker lists. */
  async function select(id: string | number) {
    try {
      const reply = await request<{ items: AssociatableItem[] }>(`${endpoint}?current=${encodeURIComponent(String(id))}`, { toastOnError: false });
      const item = reply?.items[0];

      if (item !== undefined) {
        onCreated({ value: item.value, display: item.display });
      }
    } catch {
      toast.error(__('Something went wrong. Please try again.'));
    }

    onClose();
  }

  // The portal keeps the sheet's form out of the page's form in the DOM.
  return createPortal(
    <Modal
      labelledBy={titleId}
      onClose={onClose}
      className="fixed inset-y-0 right-0 h-full w-full max-w-[480px] animate-in slide-in-from-right space-y-4 overflow-y-auto border-l border-border bg-background p-4 text-foreground shadow-lg duration-200"
    >
      <div className="flex items-center justify-between gap-2">
        <h2 id={titleId} className="text-base font-semibold">
          {__('Create :resource', { resource: label.toLowerCase() })}
        </h2>
        <button type="button" aria-label={__('Close')} className="rounded-md p-1 hover:bg-muted" onClick={onClose}>
          <X aria-hidden="true" className="size-4" />
        </button>
      </div>
      {form === null ? (
        <p className="text-sm text-muted-foreground">{__('Loading…')}</p>
      ) : (
        <CreateForm uriKey={uriKey} singularLabel={label} form={form} onSaved={select} onCancel={onClose} />
      )}
    </Modal>,
    document.body,
  );
}

interface CreateFormProps {
  uriKey: string;
  singularLabel: string;
  form: FormPayload;
  onSaved: (id: string | number) => Promise<void>;
  onCancel: () => void;
}

function CreateForm({ uriKey, singularLabel, form, onSaved, onCancel }: CreateFormProps) {
  const { __ } = useTranslation();
  const [saved, setSaved] = useState(false);
  const record = useRecordForm({ resource: { uriKey, label: singularLabel, singularLabel, abilities: { create: true } }, mode: 'create', id: null, form, drafts: false }, (_redirect, id) => {
    setSaved(true);
    void onSaved(id);
  });

  return (
    <form
      noValidate
      className="space-y-4"
      onSubmit={(event) => {
        event.preventDefault();
        // The sheet sits inside the page's form in the React tree, and a submit bubbles up it.
        event.stopPropagation();
        void record.submit();
      }}
    >
      <FormFields uriKey={uriKey} mode="create" id={null} record={record} idPrefix={`inline-${uriKey}`} />
      <div className="flex justify-end gap-2">
        <button type="button" className="h-9 rounded-md px-4 text-sm" onClick={onCancel}>
          {__('Cancel')}
        </button>
        <button
          type="submit"
          disabled={record.busy || saved}
          className="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-60"
        >
          {__('Create')}
        </button>
      </div>
    </form>
  );
}
