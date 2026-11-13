import { ApiError, request, toast, useLathe, useTranslation } from '@lathephp/core';
import { useCallback, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { draftIdOf } from '../fields/rich/drafts';
import { asFile } from '../fields/upload/file';
import type { FormUploads } from '../fields/upload/uploads';
import { persistable, readDraft, useFormDraft } from './useFormDraft';
import type { FormFieldData, FormPayload, FormProps } from '../fields/types';

export type SubmitIntent = 'default' | 'another' | 'continue';

interface WriteResult {
  id: string | number;
  redirect: string;
  /** The record's new token, which an update answers with. */
  updatedAt?: string | null;
}

/** The starting values of a form: each field's value, keyed by attribute. */
export function initialValues(fields: FormFieldData[]): Record<string, unknown> {
  return Object.fromEntries(fields.map((field) => [field.attribute, field.value]));
}

function isPlain(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && Object.getPrototypeOf(value) === Object.prototype;
}

function isBlank(value: unknown): boolean {
  return value == null || value === '' || (Array.isArray(value) && value.length === 0) || (isPlain(value) && Object.keys(value).length === 0);
}

/** Whether two values hold the same: blanks are alike, and so are a number and the text an input sends for it. */
function same(a: unknown, b: unknown): boolean {
  if (a === b || (isBlank(a) && isBlank(b))) {
    return true;
  }

  if (typeof a === 'number' || typeof b === 'number') {
    return String(a) === String(b);
  }

  if (Array.isArray(a) && Array.isArray(b)) {
    return a.length === b.length && a.every((item, at) => same(item, b[at]));
  }

  return isPlain(a) && isPlain(b) && [...Object.keys(a), ...Object.keys(b)].every((key) => same(a[key], b[key]));
}

/** Components whose value names related records with keys only shown: their name and link. */
const RELATION_COMPONENTS = new Set(['belongs-to-field', 'morph-to-field', 'tag-field']);

/** A related record as its key alone: a record loads with its link, and the picker gives it without. */
function keyOnly(value: unknown): unknown {
  if (Array.isArray(value)) {
    return value.map(keyOnly);
  }

  if (!isPlain(value)) {
    return value;
  }

  return Object.fromEntries(Object.entries(value).filter(([key]) => key !== 'display' && key !== 'href'));
}

/**
 * Whether the values differ from the ones the fields loaded with. A key-value field loads an
 * object and edits it as a list of key and value rows, so an object on either side is read as those rows.
 * A relation compares the records it names, not how it shows them.
 */
export function formChanged(values: Record<string, unknown>, fields: FormFieldData[]): boolean {
  return fields.some((field) => {
    const rows = (value: unknown) => {
      if (field.component === 'key-value-field' && isPlain(value)) {
        return Object.entries(value).map(([key, item]) => ({ key, value: item }));
      }

      return RELATION_COMPONENTS.has(field.component) ? keyOnly(value) : value;
    };

    return !same(rows(values[field.attribute]), rows(field.value));
  });
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
 * must neither restore nor clear the draft of the form behind it. `user` is the page's signed-in
 * email: a draft another user typed never opens.
 */
export function useRecordForm(
  { resource, mode, id, form, via = null, drafts = true, user = null }: Pick<FormProps, 'resource' | 'mode' | 'id' | 'form'> & { via?: string | null; drafts?: boolean; user?: string | null },
  onDone: (redirect: string, id: string | number) => void,
) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const [payload, setPayload] = useState<FormPayload>(form);
  const attributes = persistable(form.fields);
  const [saved] = useState(() => (drafts ? readDraft(attributes, user) : null));
  const [values, setValuesState] = useState<Record<string, unknown>>(() => ({ ...initialValues(form.fields), ...saved }));
  // The values as of now, so two setValue calls in one tick each see the other's change.
  const valuesRef = useRef(values);
  const setValues = (next: Record<string, unknown>) => {
    valuesRef.current = next;
    setValuesState(next);
  };
  const [errors, setErrors] = useState<Record<string, string>>({});
  /** Counts the saves a 422 refused, so the page moves the user to the errors once per save. */
  const [failures, setFailures] = useState(0);
  const [busy, setBusy] = useState(false);
  // The file uploads still running: the save waits for them, so it carries every file chosen.
  const [uploading, setUploading] = useState(0);
  const running = useRef(0);
  const uploads = useMemo<FormUploads>(
    () => ({
      begin: () => {
        let ended = false;
        running.current += 1;
        setUploading(running.current);

        return () => {
          if (!ended) {
            ended = true;
            running.current -= 1;
            setUploading(running.current);
          }
        };
      },
    }),
    [],
  );
  /** The submit a 401 or 419 interrupted, replayed once the user signs in again. */
  const [reauth, setReauth] = useState<SubmitIntent | null>(null);
  /** A 409: someone saved the record since this form loaded. */
  const [conflict, setConflict] = useState<{ intent: SubmitIntent; updatedAt: string | null } | null>(null);
  const stored = useFormDraft(values, attributes, saved !== null);
  const draft = drafts ? stored : { restored: false, touch: () => undefined, clear: () => undefined };
  // Values that differ from the loaded ones, typed or restored, not yet saved. The ref answers at once, so the redirect a save starts
  // in the same tick already reads clean; the state redraws the footer.
  // A restored draft that holds the loaded values is no change.
  const [dirty, setDirtyState] = useState(() => saved !== null && formChanged(values, form.fields));
  const dirtyRef = useRef(dirty);
  const setDirty = (next: boolean) => {
    dirtyRef.current = next;
    setDirtyState(next);
  };
  const isDirty = useCallback(() => dirtyRef.current, []);
  const base = `${apiPath}/resources/${resource.uriKey}`;
  const updating = mode === 'update';

  const setValue = (attribute: string, value: unknown) => {
    const next = { ...valuesRef.current, [attribute]: value };
    const changed = formChanged(next, payload.fields);

    // A form back to the values it loaded with keeps no draft to restore.
    if (changed) {
      draft.touch();
    } else {
      draft.clear();
    }

    setDirty(changed);
    setValues(next);
    // A changed field's message is stale: drop it and the ones under its nested keys.
    setErrors((current) => {
      const kept = Object.entries(current).filter(([key]) => key !== attribute && !key.startsWith(`${attribute}.`));

      return kept.length === Object.keys(current).length ? current : Object.fromEntries(kept);
    });
  };

  async function submit(intent: SubmitIntent = 'default', token: string | null | undefined = payload.updatedAt): Promise<void> {
      // An Enter in a text field submits too; a save sent now would leave the running upload's file out.
      if (running.current > 0) {
        return;
      }

      setBusy(true);
      setErrors({});

      try {
        const result = await request<WriteResult>(updating ? `${base}/${String(id)}` : via === null ? base : `${base}?via=${encodeURIComponent(via)}`, {
          method: updating ? 'PUT' : 'POST',
          toastOnError: false,
          body: updating && token !== undefined ? { ...submittable(values, payload.fields, resource.uriKey), _updated_at: token } : submittable(values, payload.fields, resource.uriKey),
        });

        draft.clear();
        setDirty(false);

        if (intent === 'another' && !updating) {
          setValues(initialValues(form.fields));
          toast.success(__(':resource created.', { resource: resource.singularLabel }));
        } else if (intent === 'continue' && updating) {
          // The save passed whatever the refresh does: without fresh fields the form keeps the values
          // it saved and the token the save answered, so the next save meets no conflict of its own.
          const fresh = await request<FormPayload>(`${base}/${String(id)}/update-fields`, { toastOnError: false }).catch(() => null);

          if (fresh !== null) {
            setPayload(fresh);
            setValues(initialValues(fresh.fields));
          } else if (result?.updatedAt !== undefined) {
            const token = result.updatedAt;
            setPayload((current) => ({ ...current, updatedAt: token }));
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
          setFailures((count) => count + 1);
          toast.error(__('Please fix the highlighted fields.'));
        } else {
          toast.error(__('Something went wrong. Please try again.'), {
            action: {
              label: __('Retry'),
              // The render that failed is gone by the click: its values and token may be stale.
              onClick: () => {
                void latestSubmit.current(intent);
              },
            },
          });
        }
      } finally {
        setBusy(false);
      }
  }

  // Read when a toast's Retry is clicked, so it sends what the form holds then.
  const latestSubmit = useRef(submit);
  useLayoutEffect(() => {
    latestSubmit.current = submit;
  });

  return {
    payload,
    values,
    setValue,
    errors,
    failures,
    busy,
    /** Whether a file is still uploading; Save waits for it. */
    uploading: uploading > 0,
    /** What the form's file fields count their uploads with. */
    uploads,
    dirty,
    /** The dirty flag as of now, for a handler that runs before the next render. */
    isDirty,
    submit,
    reauth,
    draft: {
      restored: draft.restored,
      discard: () => {
        draft.clear();
        setDirty(false);
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
      setDirty(false);
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
        setValues({ ...valuesRef.current, ...changed });
      }
    },
  };
}
