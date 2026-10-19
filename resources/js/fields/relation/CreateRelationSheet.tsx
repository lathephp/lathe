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

  // Sentence case, as the create form's title: a capitalised word lowers its first letter, an acronym keeps its case.
  const title = __('Create :resource', { resource: label.replace(/\b[A-Z](?=[a-z])/g, (letter) => letter.toLowerCase()) });

  // The portal keeps the sheet's form out of the page's form in the DOM.
  return createPortal(
    <Modal
      labelledBy={titleId}
      onClose={onClose}
      className="fixed inset-y-0 right-0 flex h-full w-full max-w-[480px] animate-in flex-col rounded-s-2xl border-s border-border bg-popover text-popover-foreground shadow-lg duration-200 slide-in-from-right"
    >
      <div className="flex items-start gap-3 border-b border-border px-6 py-5">
        <h2 id={titleId} className="text-base leading-[22px] font-semibold">
          {title}
        </h2>
        <button
          type="button"
          aria-label={__('Close')}
          className="ms-auto inline-flex size-[30px] shrink-0 items-center justify-center rounded-md text-muted-foreground outline-none hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-focus"
          onClick={onClose}
        >
          <X aria-hidden="true" strokeWidth={1.75} className="size-4" />
        </button>
      </div>
      {form === null ? (
        <p className="p-6 text-sm text-muted-foreground">{__('Loading…')}</p>
      ) : (
        <CreateForm uriKey={uriKey} singularLabel={label} submitLabel={title} form={form} onSaved={select} onCancel={onClose} />
      )}
    </Modal>,
    document.body,
  );
}

interface CreateFormProps {
  uriKey: string;
  singularLabel: string;
  submitLabel: string;
  form: FormPayload;
  onSaved: (id: string | number) => Promise<void>;
  onCancel: () => void;
}

function CreateForm({ uriKey, singularLabel, submitLabel, form, onSaved, onCancel }: CreateFormProps) {
  const { __ } = useTranslation();
  const [saved, setSaved] = useState(false);
  const record = useRecordForm({ resource: { uriKey, label: singularLabel, singularLabel, abilities: { create: true } }, mode: 'create', id: null, form, drafts: false }, (_redirect, id) => {
    setSaved(true);
    void onSaved(id);
  });

  return (
    <form
      noValidate
      className="flex min-h-0 flex-1 flex-col"
      onSubmit={(event) => {
        event.preventDefault();
        // The sheet sits inside the page's form in the React tree, and a submit bubbles up it.
        event.stopPropagation();
        void record.submit();
      }}
    >
      <div className="min-h-0 flex-1 overflow-y-auto p-6">
        <FormFields uriKey={uriKey} mode="create" id={null} record={record} idPrefix={`inline-${uriKey}`} bare />
      </div>
      <div className="flex justify-end gap-2 border-t border-border px-6 py-4">
        <button type="button" className="lathe-button lathe-button-outline lathe-button-size-default" onClick={onCancel}>
          {__('Cancel')}
        </button>
        <button type="submit" disabled={record.busy || saved} className="lathe-button lathe-button-default lathe-button-size-default">
          {submitLabel}
        </button>
      </div>
    </form>
  );
}
