import { ApiError, request, toast, useLathe, useTranslation } from '@lathephp/core';
import { useState } from 'react';
import { draftIdOf } from '../fields/rich/drafts';
import { asFile } from '../fields/upload/file';
import { persistable, readDraft, useFormDraft } from './useFormDraft';
import type { FormFieldData, FormPayload, FormProps } from '../fields/types';

export type SubmitIntent = 'default' | 'another' | 'continue';

interface WriteResult {
  id: string | number;
  redirect: string;
}

/** The starting values of a form: each field's value, keyed by attribute. */
export function initialValues(fields: FormFieldData[]): Record<string, unknown> {
  return Object.fromEntries(fields.map((field) => [field.attribute, field.value]));
}

/** Components whose value is a stored file; the same list as the draft's. */
const FILE_COMPONENTS = new Set(['file-field', 'image-field', 'audio-field']);

/**
 * The values as the server reads them: a file field cleared of the file it started with sends
 * `<attribute>_removed`, because a bare null means "keep the stored file"; an editor that
 * uploaded images sends `<attribute>DraftId`, so the save attaches them.
 */
function submittable(values: Record<string, unknown>, fields: FormFieldData[], resource: string): Record<string, unknown> {
  const removed = fields
    .filter((field) => FILE_COMPONENTS.has(field.component) && asFile(field.value) !== null && values[field.attribute] === null)
    .map((field): [string, number] => [`${field.attribute}_removed`, 1]);
  const drafts = fields.flatMap((field): [string, string][] => {
    const draftId = field.withFiles === true ? draftIdOf(resource, field.attribute) : undefined;

    return draftId === undefined ? [] : [[`${field.attribute}DraftId`, draftId]];
  });

  return { ...values, ...Object.fromEntries(removed), ...Object.fromEntries(drafts) };
}

/** The first message of each attribute in a 422 body. */
export function firstErrors(data: unknown): Record<string, string> {
  const errors = (data as { errors?: Record<string, string[]> } | null)?.errors ?? {};

  return Object.fromEntries(
    Object.entries(errors).flatMap(([key, messages]) => (messages[0] === undefined ? [] : [[key, messages[0]]])),
  );
}

/**
 * The state and submit of a create or edit form. A failed submit keeps every typed value:
 * a 422 shows the messages under their fields, any other failure toasts with Retry.
 * A create under a `via` sends it back, so the server forces the parent it names.
 * A form in a modal passes `drafts: false`: the draft key is the page's, and the modal
 * must neither restore nor clear the draft of the form behind it.
 */
export function useRecordForm(
  { resource, mode, id, form, via = null, drafts = true }: Pick<FormProps, 'resource' | 'mode' | 'id' | 'form'> & { via?: string | null; drafts?: boolean },
  onDone: (redirect: string, id: string | number) => void,
) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const [payload, setPayload] = useState<FormPayload>(form);
  const attributes = persistable(form.fields);
  const [saved] = useState(() => (drafts ? readDraft(attributes) : null));
  const [values, setValues] = useState<Record<string, unknown>>(() => ({ ...initialValues(form.fields), ...saved }));
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);
  /** The submit a 401 or 419 interrupted, replayed once the user signs in again. */
  const [reauth, setReauth] = useState<SubmitIntent | null>(null);
  /** A 409: someone saved the record since this form loaded. */
  const [conflict, setConflict] = useState<{ intent: SubmitIntent; updatedAt: string | null } | null>(null);
  const stored = useFormDraft(values, attributes, saved !== null);
  const draft = drafts ? stored : { restored: false, touch: () => undefined, clear: () => undefined };
  const base = `${apiPath}/resources/${resource.uriKey}`;
  const updating = mode === 'update';

  const setValue = (attribute: string, value: unknown) => {
    draft.touch();
    setValues((current) => ({ ...current, [attribute]: value }));
  };

  async function submit(intent: SubmitIntent = 'default', token: string | null | undefined = payload.updatedAt): Promise<void> {
      setBusy(true);
      setErrors({});

      try {
        const result = await request<WriteResult>(updating ? `${base}/${String(id)}` : via === null ? base : `${base}?via=${encodeURIComponent(via)}`, {
          method: updating ? 'PUT' : 'POST',
          toastOnError: false,
          body: updating && token !== undefined ? { ...submittable(values, payload.fields, resource.uriKey), _updated_at: token } : submittable(values, payload.fields, resource.uriKey),
        });

        draft.clear();

        if (intent === 'another' && !updating) {
          setValues(initialValues(form.fields));
          toast.success(__(':resource created.', { resource: resource.singularLabel }));
        } else if (intent === 'continue' && updating) {
          const fresh = await request<FormPayload>(`${base}/${String(id)}/update-fields`, { toastOnError: false });

          if (fresh !== null) {
            setPayload(fresh);
            setValues(initialValues(fresh.fields));
          }

          toast.success(__(':resource updated.', { resource: resource.singularLabel }));
        } else if (result !== null) {
          onDone(result.redirect, result.id);
        }
      } catch (error) {
        if (error instanceof ApiError && error.status === 409) {
          setConflict({ intent, updatedAt: (error.data as { updatedAt?: string | null } | null)?.updatedAt ?? null });
        } else if (error instanceof ApiError && (error.status === 401 || error.status === 419)) {
          setReauth(intent);
        } else if (error instanceof ApiError && error.status === 422) {
          setErrors(firstErrors(error.data));
          toast.error(__('Please fix the highlighted fields.'));
        } else {
          toast.error(__('Something went wrong. Please try again.'), {
            action: {
              label: __('Retry'),
              onClick: () => {
                void submit(intent);
              },
            },
          });
        }
      } finally {
        setBusy(false);
      }
  }

  return {
    payload,
    values,
    setValue,
    errors,
    busy,
    submit,
    reauth,
    draft: {
      restored: draft.restored,
      discard: () => {
        draft.clear();
        setValues(initialValues(payload.fields));
      },
    },
    /** Signed in again: replay the interrupted submit once. */
    resume: () => {
      const intent = reauth;
      setReauth(null);

      if (intent !== null) {
        void submit(intent);
      }
    },
    cancelReauth: () => {
      setReauth(null);
    },
    conflict: conflict !== null,
    /** Load the stored values again; the typed ones and the draft are dropped. */
    refresh: async () => {
      setConflict(null);
      draft.clear();
      const fresh = await request<FormPayload>(`${base}/${String(id)}/update-fields`);

      if (fresh !== null) {
        setPayload(fresh);
        setValues(initialValues(fresh.fields));
      }
    },
    /** Save the typed values over the other change, with the token the conflict returned. */
    override: () => {
      if (conflict === null) {
        return;
      }

      setConflict(null);
      setPayload((current) => ({ ...current, updatedAt: conflict.updatedAt }));
      void submit(conflict.intent, conflict.updatedAt);
    },
    cancelConflict: () => {
      setConflict(null);
    },
    /** Values the server set (dependsOn), merged without counting as typed. */
    applyValues: (changed: Record<string, unknown>) => {
      if (Object.keys(changed).length > 0) {
        setValues((current) => ({ ...current, ...changed }));
      }
    },
  };
}
