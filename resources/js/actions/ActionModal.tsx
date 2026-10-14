import { ApiError, request, toast, useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { useEffect, useId, useState } from 'react';
import { Modal } from '../components/Modal';
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
  onClose: () => void;
  /** Runs the action with the form's values; a 422 rejects with its ApiError and the modal shows it. */
  onRun: (fields: Record<string, unknown>) => Promise<void>;
}

const sizes: Record<string, string> = {
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
export function ActionModal({ base, action, count, onClose, onRun }: ActionModalProps) {
  const { __ } = useTranslation();
  const titleId = useId();
  const [form, setForm] = useState<ActionForm | null>(null);
  const [values, setValues] = useState<Record<string, unknown>>({});
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let live = true;

    request<ActionForm>(`${base}/actions/${action.uriKey}/form?count=${String(count)}`)
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
  }, [base, action.uriKey, count, __, onClose]);

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
      className={cn(
        'relative w-full overflow-y-auto rounded-lg border border-border bg-background p-4 text-foreground shadow-lg',
        action.style === 'fullscreen' ? 'h-[85vh] max-w-none' : (sizes[action.size] ?? 'max-w-2xl'),
      )}
    >
      <form
        className="space-y-4"
        onSubmit={(event) => {
          event.preventDefault();
          void submit();
        }}
      >
        <h2 id={titleId} className="text-base font-semibold">
          {action.name}
        </h2>
        {text !== null && <p className="text-sm text-muted-foreground">{text}</p>}
        {errors.selection !== undefined && (
          <p role="alert" className="text-sm text-destructive">
            {errors.selection}
          </p>
        )}
        {form !== null && form.fields.length > 0 && (
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
                }}
              />
            ))}
          </div>
        )}
        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" size="sm" onClick={onClose}>
            {action.cancelButtonText}
          </Button>
          {/* The ui Button has no destructive variant, and its classes do not merge with an override. */}
          <button
            type="submit"
            disabled={busy || form === null}
            className={cn(
              'inline-flex h-8 items-center rounded-md px-3 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50',
              action.destructive ? 'bg-destructive text-white hover:bg-destructive/90' : 'bg-primary text-primary-foreground hover:bg-primary/90',
            )}
          >
            {busy && action.asBlob ? __('Preparing file') : action.confirmButtonText}
          </button>
        </div>
      </form>
    </Modal>
  );
}
