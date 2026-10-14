import { request, toast, useLathe, useTranslation } from '@lathephp/core';
import { useEffect, useId, useState } from 'react';
import { createPortal } from 'react-dom';
import { Modal } from '../../components/Modal';
import { FormFields } from '../../records/FormFields';
import { useRecordForm } from '../../records/useRecordForm';
import type { FieldSchema, FormPayload } from '../types';
import { useAssociatableEndpoint, type AssociatableItem, type LookupRecord } from './useAssociatable';

/** Written out whole, so Tailwind finds each class. */
const widths: Record<string, string> = {
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-lg',
  xl: 'max-w-xl',
  '2xl': 'max-w-2xl',
  '3xl': 'max-w-3xl',
  '4xl': 'max-w-4xl',
  '5xl': 'max-w-5xl',
  '6xl': 'max-w-6xl',
  '7xl': 'max-w-7xl',
};

interface CreateRelationModalProps {
  field: FieldSchema;
  record?: LookupRecord;
  /** The record that was just created, as the picker holds a chosen one. */
  onCreated: (item: { value: string | number; display: string }) => void;
  onClose: () => void;
}

/** The related Resource's create form in a modal; saving selects the new record in the field. */
export function CreateRelationModal({ field, record, onCreated, onClose }: CreateRelationModalProps) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const titleId = useId();
  const endpoint = useAssociatableEndpoint(field, record);
  const uriKey = String(field.resource);
  const label = typeof field.singularLabel === 'string' ? field.singularLabel : field.label;
  const [form, setForm] = useState<FormPayload | null>(null);

  useEffect(() => {
    const abort = new AbortController();

    request<FormPayload>(`${apiPath}/resources/${uriKey}/creation-fields`, { signal: abort.signal }).then(setForm, () => {
      if (!abort.signal.aborted) {
        toast.error(__('Something went wrong. Please try again.'));
        onClose();
      }
    });

    return () => {
      abort.abort();
    };
    // The form loads once, when the modal opens.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  /** The new record's picker value comes from the same route the picker lists. */
  async function select(id: string | number) {
    try {
      const reply = await request<{ items: AssociatableItem[] }>(`${endpoint}?current=${encodeURIComponent(String(id))}`);
      const item = reply?.items[0];

      if (item !== undefined) {
        onCreated({ value: item.value, display: item.display });
      }
    } catch {
      toast.error(__('Something went wrong. Please try again.'));
    }

    onClose();
  }

  return createPortal(
    <Modal
      labelledBy={titleId}
      onClose={onClose}
      className={`relative w-full ${widths[typeof field.modalSize === 'string' ? field.modalSize : 'lg'] ?? 'max-w-lg'} space-y-4 rounded-lg border border-border bg-background p-4 text-foreground shadow-lg`}
    >
      <h2 id={titleId} className="text-base font-semibold">
        {__('Create :resource', { resource: label })}
      </h2>
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
        // The modal sits inside the page's form in the React tree, and a submit bubbles up it.
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
