import { Head, Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { useEffect, useRef } from 'react';
import { FieldError } from '../../components/FieldError';
import { FormField } from '../../fields/registry';
import type { FormFieldData, FormProps } from '../../fields/types';
import { cn } from '../../lib/cn';
import { Breadcrumbs } from '../../records/Breadcrumbs';
import { RecordLayout } from '../../records/RecordLayout';
import { useRecordForm, type SubmitIntent } from '../../records/useRecordForm';
import type { SharedProps } from '../../types';

/** The create and edit form of a Resource. */
export default function Form({ resource, mode, id, title, breadcrumbs, form, submitLabel }: FormProps) {
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
  const formRef = useRef<HTMLFormElement>(null);
  const record = useRecordForm({ resource, mode, id, form }, (redirect) => {
    router.visit(`${basePath}/resources/${redirect}`);
  });
  const byKey = new Map(record.payload.fields.map((field) => [field.key, field]));
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
        <RecordLayout
          blocks={record.payload.layout}
          renderField={(key) => {
            const field = byKey.get(key);

            return field === undefined ? null : (
              <FormRow
                field={field}
                value={record.values[field.attribute]}
                error={record.errors[field.attribute]}
                onChange={(value) => {
                  record.setValue(field.attribute, value);
                }}
              />
            );
          }}
        />
        <div className="flex flex-wrap items-center justify-end gap-2">
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
    </div>
  );
}

function FormRow({ field, value, error, onChange }: { field: FormFieldData; value: unknown; error: string | undefined; onChange: (value: unknown) => void }) {
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
