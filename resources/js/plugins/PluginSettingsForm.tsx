import { ApiError, request, toast, useLathe, useTranslation } from '@lathephp/core';
import { useEffect, useState } from 'react';
import type { FieldSchema, LayoutBlock } from '../fields/types';
import { PageHeader } from '../layout/PageHeader';
import { FormRow } from '../records/FormFields';
import { RecordLayout } from '../records/RecordLayout';
import { firstErrors } from '../records/useRecordForm';

interface SettingsPayload {
  fields: (FieldSchema & { required?: boolean; readonly?: boolean })[];
  layout: LayoutBlock[];
  values: Record<string, unknown>;
}

interface PluginSettingsFormProps {
  pluginKey: string;
  title: string;
}

/**
 * The settings form of a plugin: its settings() fields in their sections and tabs, saved with
 * one PUT. A 422 shows each message under its field; a save toasts.
 */
export default function PluginSettingsForm({ pluginKey, title }: PluginSettingsFormProps) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const endpoint = `${apiPath}/plugins/${pluginKey}/settings`;
  const [payload, setPayload] = useState<SettingsPayload | null>(null);
  const [values, setValues] = useState<Record<string, unknown>>({});
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    void request<SettingsPayload>(endpoint).then((data) => {
      if (data !== null) {
        setPayload(data);
        setValues(data.values);
      }
    });
  }, [endpoint]);

  async function save() {
    setBusy(true);
    setErrors({});

    try {
      await request(endpoint, { method: 'PUT', body: values, toastOnError: false });
      toast.success(__('Settings saved.'));
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        setErrors(firstErrors(error.data));
        toast.error(__('Please fix the highlighted fields.'));
      } else {
        toast.error(__('Something went wrong. Please try again.'));
      }
    } finally {
      setBusy(false);
    }
  }

  const byKey = new Map((payload?.fields ?? []).map((field) => [field.key, field]));
  const errorKeys = new Set(Object.keys(errors));

  return (
    <div className="space-y-6">
      <PageHeader title={__(':plugin settings', { plugin: title })} />
      {payload === null ? (
        <div aria-hidden="true" className="h-64 rounded-xl bg-muted" />
      ) : (
        <form
          noValidate
          className="space-y-6"
          onSubmit={(event) => {
            event.preventDefault();
            void save();
          }}
        >
          <RecordLayout
            blocks={payload.layout}
            errorKeys={errorKeys}
            renderField={(key) => {
              const field = byKey.get(key);

              return field === undefined ? null : (
                <FormRow
                  field={{ ...field, value: values[field.attribute] ?? null }}
                  idPrefix="setting"
                  value={values[field.attribute] ?? null}
                  values={values}
                  error={errors[field.attribute]}
                  onChange={(value) => {
                    setValues((current) => ({ ...current, [field.attribute]: value }));
                  }}
                />
              );
            }}
          />
          <div className="flex justify-end">
            <button
              type="submit"
              disabled={busy}
              className="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-60"
            >
              {__('Save')}
            </button>
          </div>
        </form>
      )}
    </div>
  );
}
