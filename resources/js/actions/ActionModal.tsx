import { ApiError, formatNumber, request, toast, useTranslation } from '@lathephp/core';
import { useEffect, useId, useState } from 'react';
import { Modal, ModalClose, ModalFooter, ModalHeader, ModalTitle } from '../components/Modal';
import { AssociatableEndpoint } from '../fields/relation/useAssociatable';
import type { FormFieldData } from '../fields/types';
import { FormRow } from '../records/FormFields';
import { firstErrors, initialValues } from '../records/useRecordForm';
import type { ActionMeta } from './types';
import { Button } from '../components/ui/Button';

interface ActionForm {
  fields: FormFieldData[];
  confirmText: string | null;
}

interface ActionModalProps {
  /** The API base of the Resource or lens: .../resources/orders or .../resources/orders/lenses/{lens}. */
  base: string;
  action: ActionMeta;
  count: number;
  /** The count as the confirm shows it, such as 10,000+ for a capped count; the number formatted by default. */
  label?: string;
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
export function ActionModal({ base, action, count, label = formatNumber(count), resourceId, onClose, onRun }: ActionModalProps) {
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

  // The page fills a plain confirm itself, since the server's number has no + for a capped count.
  // A :title, or a confirm a closure writes, needs the server's text.
  const own = action.confirmText !== null && !action.confirmText.includes(':title');
  const text = (own ? null : form?.confirmText) ?? action.confirmText?.replace(':count', label) ?? null;

  return (
    <Modal
      labelledBy={titleId}
      onClose={onClose}
      locked={busy}
      // The overlay scrolls a tall form, so a picker's list can hang below the dialog unclipped.
      className={action.style === 'fullscreen' ? 'h-[85vh] max-w-none overflow-y-auto' : (sizes[action.size] ?? sizes.medium)}
    >
      <form
        className="contents"
        onSubmit={(event) => {
          event.preventDefault();
          void submit();
        }}
      >
        {/* A confirm asks its question as the title; a form without one is titled by the action. */}
        <ModalHeader>
          <ModalTitle id={titleId}>{text ?? action.name}</ModalTitle>
        </ModalHeader>
        {errors.selection !== undefined && (
          <p role="alert" className="text-sm text-destructive">
            {errors.selection}
          </p>
        )}
        {form !== null && form.fields.length > 0 && (
          // A relation field looks its options up through the action, which declares it.
          <AssociatableEndpoint value={(attribute) => `${base}/action/associatable?${new URLSearchParams({ action: action.uriKey, field: attribute }).toString()}`}>
            {/* The rows sit 14 px apart with the label 6 px over its control. */}
            <div className="-my-[7px] flex flex-col [&>*]:gap-1.5 [&>*]:py-[7px]">
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
        <ModalFooter>
          <Button variant="outline" onClick={onClose}>
            {action.cancelButtonText}
          </Button>
          {/* A destructive action confirms with the solid destructive button. */}
          <Button type="submit" variant={action.destructive ? 'destructive-solid' : 'default'} disabled={busy || form === null}>
            {busy && action.asBlob ? __('Preparing file') : action.confirmButtonText}
          </Button>
        </ModalFooter>
      </form>
      {form !== null && form.fields.length > 0 && <ModalClose onClick={onClose} />}
    </Modal>
  );
}
