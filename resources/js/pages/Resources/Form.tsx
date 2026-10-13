import { Head, Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { useEffect, useMemo, useRef, useState } from 'react';
import { FieldError } from '../../components/FieldError';
import { passes } from '../../fields/conditions';
import { FormField, stringValue } from '../../fields/registry';
import { RepeaterErrorsContext } from '../../fields/repeater/errors';
import type { FormFieldData, FormProps } from '../../fields/types';
import { cn } from '../../lib/cn';
import { Breadcrumbs } from '../../records/Breadcrumbs';
import { ConflictDialog } from '../../records/ConflictDialog';
import { ReauthModal } from '../../records/ReauthModal';
import { useDependentFields } from '../../records/useDependentFields';
import { RecordLayout } from '../../records/RecordLayout';
import { useRecordForm, type SubmitIntent } from '../../records/useRecordForm';
import type { SharedProps } from '../../types';

/** The create and edit form of a Resource. */
export default function Form({ resource, mode, id, title, breadcrumbs, form, submitLabel }: FormProps) {
  const { __ } = useTranslation();
  const { basePath, auth } = usePage<SharedProps>().props;
  const formRef = useRef<HTMLFormElement>(null);
  const record = useRecordForm({ resource, mode, id, form }, (redirect) => {
    router.visit(`${basePath}/resources/${redirect}`);
  });
  const byKey = new Map(record.payload.fields.map((field) => [field.key, field]));
  // The attributes the user typed in: a dependsOn patch never overwrites them.
  const [dirty, setDirty] = useState<ReadonlySet<string>>(() => new Set());
  const patches = useDependentFields({
    uriKey: resource.uriKey,
    mode,
    id,
    fields: record.payload.fields,
    values: record.values,
    dirty,
    apply: record.applyValues,
  });
  // One Set per failed submit, so the layout opens the marked tab once.
  const errorKeys = useMemo(() => new Set(Object.keys(record.errors).map((key) => key.split('.')[0] ?? key)), [record.errors]);
  const updating = mode === 'update';
  const cancelHref = updating ? `${basePath}/resources/${resource.uriKey}/${String(id)}` : `${basePath}/resources/${resource.uriKey}`;

  // A failed submit moves focus to the first field it marked.
  useEffect(() => {
    if (Object.keys(record.errors).length > 0) {
      formRef.current?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus();
    }
  }, [record.errors]);

  function submit(intent: SubmitIntent) {
    void record.submit(intent);
  }

  return (
    <div className="space-y-6">
      <Head title={title} />
      <Breadcrumbs items={breadcrumbs} />
      <h1 className="text-xl font-semibold">{title}</h1>
      <form
        ref={formRef}
        noValidate
        className="space-y-6"
        onSubmit={(event) => {
          event.preventDefault();
          submit('default');
        }}
      >
        <RepeaterErrorsContext value={record.errors}>
          <RecordLayout
            blocks={record.payload.layout}
            borderless={record.payload.borderless === true}
            errorKeys={errorKeys}
            renderField={(key) => {
              const field = byKey.get(key);

              const patch = patches[key] ?? {};

              if (field === undefined || patch.visible === false || (patch.visible !== true && field.visibleWhen != null && !passes(field.visibleWhen, record.values))) {
                return null;
              }

              const from = field.optionsFrom as { field: string; map: Record<string, unknown> } | null | undefined;
              const decided = {
                ...field,
                ...(from == null ? {} : { options: from.map[stringValue(record.values[from.field])] ?? [] }),
                ...(patch.options === undefined ? {} : { options: patch.options }),
                required: patch.required ?? (field.required === true || (field.requiredWhen != null && passes(field.requiredWhen, record.values))),
                readonly: patch.readonly ?? (field.readonly === true || (field.readonlyWhen != null && passes(field.readonlyWhen, record.values))),
              };

              return (
                <FormRow
                  field={decided}
                  value={record.values[field.attribute]}
                  values={record.values}
                  error={record.errors[field.attribute]}
                  onChange={(value) => {
                    setDirty((current) => new Set(current).add(field.attribute));
                    record.setValue(field.attribute, value);
                  }}
                />
              );
            }}
          />
        </RepeaterErrorsContext>
        <div className="flex flex-wrap items-center justify-end gap-2">
          {record.draft.restored ? (
            <button type="button" className="me-auto h-9 rounded-md px-4 text-sm" onClick={record.draft.discard}>
              {__('Discard draft')}
            </button>
          ) : null}
          <Link href={cancelHref} className="inline-flex h-9 items-center rounded-md px-4 text-sm">
            {__('Cancel')}
          </Link>
          <button
            type="button"
            disabled={record.busy}
            className="inline-flex h-9 items-center rounded-md border border-input px-4 text-sm font-medium disabled:opacity-60"
            onClick={() => {
              submit(updating ? 'continue' : 'another');
            }}
          >
            {updating ? __('Update & Continue Editing') : __('Create & Add Another')}
          </button>
          <button
            type="submit"
            disabled={record.busy}
            className="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-60"
          >
            {submitLabel}
          </button>
        </div>
      </form>
      {record.conflict ? (
        <ConflictDialog
          onRefresh={() => {
            void record.refresh();
          }}
          onOverride={record.override}
          onClose={record.cancelConflict}
        />
      ) : null}
      {record.reauth === null ? null : (
        <ReauthModal
          email={auth.user?.email ?? ''}
          onSignedIn={record.resume}
          onOtherUser={() => {
            record.cancelReauth();
            router.reload();
          }}
          onClose={record.cancelReauth}
        />
      )}
    </div>
  );
}

function FormRow({ field, value, values, error, onChange }: { field: FormFieldData; value: unknown; values: Record<string, unknown>; error: string | undefined; onChange: (value: unknown) => void }) {
  const id = `field-${field.attribute}`;
  const helpId = field.help === null ? undefined : `${id}-help`;
  const errorId = error === undefined ? undefined : `${id}-error`;
  const describedBy = [helpId, errorId].filter((part) => part !== undefined).join(' ') || undefined;

  return (
    <div className={cn('gap-2 px-4 py-3 text-sm', field.stacked ? 'flex flex-col' : 'grid md:grid-cols-[minmax(0,1fr)_minmax(0,3fr)]')}>
      {field.compact ? null : (
        <label htmlFor={id} className="pt-2 font-medium">
          {field.label}
          {field.required === true ? (
            <span aria-hidden="true" className="ms-0.5 text-destructive">
              *
            </span>
          ) : null}
        </label>
      )}
      <div className="min-w-0 space-y-1">
        <FormField
          field={field}
          value={value}
          values={values}
          onChange={onChange}
          id={id}
          describedBy={describedBy}
          invalid={error !== undefined}
          disabled={field.readonly === true}
        />
        {helpId === undefined ? null : (
          <p id={helpId} className="text-xs text-muted-foreground">
            {field.help}
          </p>
        )}
        {errorId === undefined ? null : <FieldError id={errorId} message={error} />}
      </div>
    </div>
  );
}
