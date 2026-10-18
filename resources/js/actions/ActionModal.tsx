import { ApiError, request, toast, useTranslation } from '@lathephp/core';
import { useEffect, useId, useState } from 'react';
import { Modal } from '../components/Modal';
import { AssociatableEndpoint } from '../fields/relation/useAssociatable';
import type { FormFieldData } from '../fields/types';
import { cn } from '../lib/cn';
import { FormRow } from '../records/FormFields';
import { firstErrors, initialValues } from '../records/useRecordForm';
import type { ActionMeta } from './types';

interface ActionForm {
  fields: FormFieldData[];
  confirmText: string | null;
}

interface ActionModalProps {
  /** The API base of the Resource or lens: .../resources/orders or .../resources/orders/lenses/{lens}. */
  base: string;
  action: ActionMeta;
  count: number;
  /** The record of a run on one record, so the confirm text can name it. */
  resourceId: string | number | null;
  onClose: () => void;
  /** Runs the action with the form's values; a 422 rejects with its ApiError and the modal shows it. */
  onRun: (fields: Record<string, unknown>) => Promise<void>;
}

const sizes: Record<string, string> = {
  small: 'max-w-[400px]',
  medium: 'max-w-[520px]',
  large: 'max-w-[720px]',
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

/**
 * Confirms an action and collects its fields. The fields resolve once per opening; a 422
 * shows its messages under the fields and keeps every value.
 */
export function ActionModal({ base, action, count, resourceId, onClose, onRun }: ActionModalProps) {
  const { __ } = useTranslation();
  const titleId = useId();
  const [form, setForm] = useState<ActionForm | null>(null);
  const [values, setValues] = useState<Record<string, unknown>>({});
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let live = true;

    const query = new URLSearchParams({ count: String(count), ...(resourceId === null ? {} : { resourceId: String(resourceId) }) });

    request<ActionForm>(`${base}/actions/${action.uriKey}/form?${query.toString()}`, { toastOnError: false })
      .then((loaded) => {
        if (live && loaded !== null) {
          setForm(loaded);
          setValues(initialValues(loaded.fields));
        }
      })
      .catch(() => {
        if (live) {
          toast.error(__('Something went wrong. Please try again.'));
          onClose();
        }
      });

    return () => {
      live = false;
    };
  }, [base, action.uriKey, count, resourceId, __, onClose]);

  async function submit() {
    setBusy(true);
    setErrors({});

    try {
      await onRun(values);
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        setErrors(firstErrors(error.data));
      }
    } finally {
      setBusy(false);
    }
  }

  const text = form?.confirmText ?? action.confirmText?.replace(':count', String(count)) ?? null;

  return (
    <Modal
      labelledBy={titleId}
      onClose={onClose}
      locked={busy}
      className={cn(
        'relative w-full overflow-y-auto rounded-lg border border-border bg-background p-4 text-foreground shadow-lg',
        action.style === 'fullscreen' ? 'h-[85vh] max-w-none' : (sizes[action.size] ?? sizes.medium),
      )}
    >
      <form
        className="space-y-4"
        onSubmit={(event) => {
          event.preventDefault();
          void submit();
        }}
      >
        {/* A confirm asks its question as the title; a form without one is titled by the action. */}
        <h2 id={titleId} className="text-base font-semibold">
          {text ?? action.name}
        </h2>
        {errors.selection !== undefined && (
          <p role="alert" className="text-sm text-destructive">
            {errors.selection}
          </p>
        )}
        {form !== null && form.fields.length > 0 && (
          // A relation field looks its options up through the action, which declares it.
          <AssociatableEndpoint value={(attribute) => `${base}/action/associatable?${new URLSearchParams({ action: action.uriKey, field: attribute }).toString()}`}>
            <div className="-mx-4 divide-y divide-border">
              {form.fields.map((field) => (
                <FormRow
                  key={field.attribute}
                  field={field}
                  idPrefix={`action-${action.uriKey}`}
                  value={values[field.attribute]}
                  values={values}
                  error={errors[field.attribute]}
                  onChange={(value) => {
                    setValues((current) => ({ ...current, [field.attribute]: value }));
                    // A changed field drops its old message; the next run checks it again.
                    setErrors((current) => Object.fromEntries(Object.entries(current).filter(([key]) => key !== field.attribute)));
                  }}
                />
              ))}
            </div>
          </AssociatableEndpoint>
        )}
        {/* On a phone the buttons stack 44 px tall with the action on top. */}
        <div className="flex justify-end gap-2 max-md:flex-col-reverse">
          {/* Plain buttons: the ui Button has no destructive variant, and its size classes win over an override. */}
          <button
            type="button"
            className="inline-flex h-8 items-center justify-center rounded-md border border-input px-3 text-sm font-medium outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring max-md:h-11"
            onClick={onClose}
          >
            {action.cancelButtonText}
          </button>
          <button
            type="submit"
            disabled={busy || form === null}
            className={cn(
              'inline-flex h-8 items-center justify-center rounded-md px-3 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 max-md:h-11',
              action.destructive ? 'bg-destructive text-destructive-foreground hover:bg-destructive/90' : 'bg-primary text-primary-foreground hover:bg-primary/90',
            )}
          >
            {busy && action.asBlob ? __('Preparing file') : action.confirmButtonText}
          </button>
        </div>
      </form>
    </Modal>
  );
}
