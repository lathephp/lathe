import { ApiError, formatNumber, request, toast, useLathe, useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { useEffect, useId, useRef, useState } from 'react';
import { Modal } from '../components/Modal';
import { FilePicker } from '../components/ui/FilePicker';
import { Select } from '../components/ui/Select';
import type { SelectionDescriptor } from '../index/useRowSelection';
import { uploadImportFile } from './import/uploadImportFile';
import { runAction } from './runAction';
import type { ActionMeta, ActionRun } from './types';

/** How often the dry run's status is read, as the progress toast does. */
export const IMPORT_POLL_MS = 2000;

interface ImportColumn {
  name: string;
  label: string;
  required: boolean;
}

/** The data of the upload step's import:headers-detected result. */
interface Detected {
  import_id: number;
  original_name: string;
  total_rows: number;
  headers: string[];
  preview: unknown[][];
  columns: ImportColumn[];
  auto_mapping: (string | null)[];
}

/** GET /imports/{id}. */
interface ImportStatus {
  status: string;
  created_rows: number;
  updated_rows: number;
  skipped_rows: number;
  failed_rows: number;
  errors_url: string | null;
  exception: string | null;
  first_errors: { row_number: number; errors: Record<string, string[]> }[];
}

type ImportRun = ActionRun & { import_run?: { id: number } };

interface ImportModalProps {
  /** The API base of the Resource: .../resources/products. */
  base: string;
  action: ActionMeta;
  selection: SelectionDescriptor;
  onClose: () => void;
}

function firstError(data: unknown): string | null {
  const errors = (data as { errors?: Record<string, string[] | undefined> } | null)?.errors;

  return (errors === undefined ? undefined : Object.values(errors)[0]?.[0]) ?? null;
}

function cell(value: unknown): string {
  return typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean' ? String(value) : '';
}

/**
 * The import in four steps: choose a file, which uploads in chunks; confirm the column map;
 * validate with a dry run that writes nothing; then import, which the progress toast follows.
 */
export function ImportModal({ base, action, selection, onClose }: ImportModalProps) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const titleId = useId();
  const [detected, setDetected] = useState<Detected | null>(null);
  const [map, setMap] = useState<(string | null)[]>([]);
  const [mapped, setMapped] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [report, setReport] = useState<ImportStatus | null>(null);
  const poll = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(
    () => () => {
      if (poll.current !== null) {
        clearTimeout(poll.current);
      }
    },
    [],
  );

  async function step(fields: Record<string, unknown>): Promise<ImportRun | null> {
    setBusy(true);
    setError(null);

    try {
      return await runAction(`${base}/action`, action.uriKey, selection, fields);
    } catch (failure) {
      if (failure instanceof ApiError && failure.status === 422) {
        setError(firstError(failure.data) ?? __('The upload failed.'));
      } else {
        toast.error(__('Something went wrong. Please try again.'));
      }

      return null;
    } finally {
      setBusy(false);
    }
  }

  async function choose(file: File) {
    setBusy(true);
    setError(null);
    let uploadId: string;

    try {
      uploadId = await uploadImportFile(`${base}/actions/${action.uriKey}/uploads/file`, apiPath, file);
    } catch (failure) {
      setBusy(false);
      setError(failure instanceof ApiError ? (firstError(failure.data) ?? __('The upload failed.')) : __('The upload failed.'));

      return;
    }

    const run = await step({ import_step: 'upload', file: uploadId });
    const effect = run?.result.effect;

    if (run?.result.level === 'danger') {
      setError(run.result.message);
    } else if (effect?.type === 'emit' && effect.event === 'import:headers-detected') {
      const data = effect.data as unknown as Detected;
      setDetected(data);
      setMap(data.auto_mapping);
    }
  }

  function follow(id: number) {
    poll.current = setTimeout(() => {
      request<ImportStatus>(`${apiPath}/imports/${String(id)}`, { toastOnError: false })
        .then((status) => {
          if (status !== null && (status.status === 'completed' || status.status === 'failed')) {
            setBusy(false);
            setReport(status);
          } else {
            follow(id);
          }
        })
        .catch(() => {
          follow(id);
        });
    }, IMPORT_POLL_MS);
  }

  async function validate() {
    if (detected === null) {
      return;
    }

    setReport(null);
    const run = await step({ import_step: 'dry_run', import_id: detected.import_id, column_map: map });

    if (run?.import_run !== undefined) {
      setBusy(true);
      follow(run.import_run.id);
    }
  }

  async function runImport() {
    if (detected === null) {
      return;
    }

    const run = await step({ import_step: 'import', import_id: detected.import_id, column_map: map });

    if (run?.import_run !== undefined) {
      if (run.result.message !== null) {
        toast.success(run.result.message);
      }

      window.dispatchEvent(new CustomEvent('lathe:import-started', { detail: { id: run.import_run.id } }));
      onClose();
    }
  }

  const missing = detected?.columns.filter((column) => column.required && !map.includes(column.name)) ?? [];
  const steps = [__('File'), __('Map columns'), __('Check'), __('Import')];
  const current = detected === null ? 0 : !mapped ? 1 : report?.status === 'completed' ? 3 : 2;

  return (
    <Modal labelledBy={titleId} onClose={onClose} className="relative w-full max-w-[720px] overflow-y-auto rounded-lg border border-border bg-background p-4 text-foreground shadow-lg">
      <div className="space-y-4">
        <h2 id={titleId} className="text-base font-semibold">
          {action.name}
        </h2>
        <ol aria-label={__('Steps')} className="flex flex-wrap gap-4 text-xs text-muted-foreground">
          {steps.map((label, index) => (
            <li key={label} aria-current={index === current ? 'step' : undefined} className={index === current ? 'font-medium text-foreground' : undefined}>
              {label}
            </li>
          ))}
        </ol>
        {error !== null && (
          <p role="alert" className="text-sm text-destructive">
            {error}
          </p>
        )}

        {detected === null && (
          <div className="space-y-1">
            <label htmlFor={`${titleId}-file`} className="text-sm font-medium">
              {__('File')}
            </label>
            <FilePicker
              id={`${titleId}-file`}
              accept=".csv,.xlsx"
              disabled={busy}
              onPick={(file) => {
                void choose(file);
              }}
            >
              {__('Upload')}
            </FilePicker>
            <p className="text-sm text-muted-foreground">{busy ? __('Uploading…') : __('Choose a CSV or XLSX file.')}</p>
          </div>
        )}

        {detected !== null && !mapped && (
          <>
            <table className="w-full text-sm">
              <thead>
                <tr className="text-start text-muted-foreground">
                  <th className="py-1 font-medium">{__('Column in file')}</th>
                  <th className="py-1 font-medium">{__('Import as')}</th>
                </tr>
              </thead>
              <tbody>
                {detected.headers.map((header, index) => (
                  <tr key={index}>
                    <td className="py-1">
                      <label htmlFor={`${titleId}-map-${String(index)}`}>{header}</label>
                    </td>
                    <td className="py-1">
                      <Select
                        id={`${titleId}-map-${String(index)}`}
                        value={map[index] ?? ''}
                        size="sm"
                        className="w-full"
                        // A column maps to one row, so the columns other rows took show but cannot be picked.
                        options={[
                          { value: '', label: __('Skip') },
                          ...detected.columns.map((column) => ({
                            value: column.name,
                            label: column.label,
                            disabled: map.some((name, other) => name === column.name && other !== index),
                          })),
                        ]}
                        onChange={(next) => {
                          const value = next === '' ? null : next;
                          setMap((current) => detected.headers.map((_, other) => (other === index ? value : (current[other] ?? null))));
                        }}
                      />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            {missing.length > 0 && <p className="text-sm text-muted-foreground">{__('Map every required column: :columns.', { columns: missing.map((column) => column.label).join(', ') })}</p>}
            <div className="overflow-x-auto">
              <p className="text-sm font-medium">{__('Preview')}</p>
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-start text-muted-foreground">
                    {detected.headers.map((header, index) => (
                      <th key={index} className="py-1 pe-2 font-medium">
                        {header}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {detected.preview.map((row, index) => (
                    <tr key={index}>
                      {row.map((value, column) => (
                        <td key={column} className="py-1 pe-2">
                          {cell(value)}
                        </td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </>
        )}

        {detected !== null && mapped && (
          <div className="space-y-2 text-sm">
            {report === null && <p className="text-muted-foreground">{busy ? __('Checking the file…') : __('Check the file before importing. Nothing is saved.')}</p>}
            {report?.status === 'failed' && (
              <p role="alert" className="text-destructive">
                {report.exception ?? __('Something went wrong. Please try again.')}
              </p>
            )}
            {report?.status === 'completed' && (
              <>
                <p>{__(':created will be created, :updated will be updated, :failed failed', { created: report.created_rows, updated: report.updated_rows, failed: report.failed_rows })}</p>
                {report.skipped_rows > 0 && <p>{__(':skipped will be skipped', { skipped: report.skipped_rows })}</p>}
                {report.first_errors.length > 0 && (
                  <ul className="list-disc ps-5 text-destructive">
                    {report.first_errors.map((row) => (
                      <li key={row.row_number}>
                        {__('Row :row: :errors', {
                          row: row.row_number,
                          errors: Object.entries(row.errors)
                            .map(([column, messages]) => `${column}: ${messages.join(' ')}`)
                            .join('; '),
                        })}
                      </li>
                    ))}
                  </ul>
                )}
                {report.failed_rows > 0 && report.errors_url !== null && (
                  <a href={report.errors_url} target="_blank" rel="noopener" className="text-primary underline">
                    {__('Download failed rows')}
                  </a>
                )}
              </>
            )}
          </div>
        )}

        <div className="flex justify-end gap-2 max-md:flex-col-reverse">
          <Button type="button" variant="outline" size="sm" onClick={onClose}>
            {action.cancelButtonText}
          </Button>
          {detected !== null && !mapped && (
            <Button
              type="button"
              size="sm"
              disabled={missing.length > 0}
              onClick={() => {
                setMapped(true);
              }}
            >
              {__('Continue')}
            </Button>
          )}
          {detected !== null && mapped && (
            <>
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={busy}
                onClick={() => {
                  setMapped(false);
                  setReport(null);
                }}
              >
                {__('Back')}
              </Button>
              <Button type="button" size="sm" disabled={busy} onClick={() => void validate()}>
                {__('Check :count rows', { count: formatNumber(detected.total_rows) })}
              </Button>
              {report?.status === 'completed' && (
                <Button type="button" size="sm" disabled={busy} onClick={() => void runImport()}>
                  {__('Import')}
                </Button>
              )}
            </>
          )}
        </div>
      </div>
    </Modal>
  );
}
