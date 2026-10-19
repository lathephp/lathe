import { Link } from '@inertiajs/react';
import { request, useLathe, useTranslation } from '@lathephp/core';
import { useEffect, useId, useState } from 'react';
import { Modal, modalPanel, outlineButton, primaryButton } from '../components/Modal';
import { DetailField } from '../fields/registry';
import type { DetailFieldData } from '../fields/types';

interface PreviewPayload {
  id: string | number;
  title: string;
  subtitle: string;
  fields: DetailFieldData[];
}

interface PreviewModalProps {
  uriKey: string;
  id: string | number;
  onClose: () => void;
}

/** One record's preview fields, from one request; the list behind it does not reload. */
export function PreviewModal({ uriKey, id, onClose }: PreviewModalProps) {
  const { __ } = useTranslation();
  const { apiPath, basePath } = useLathe();
  const titleId = useId();
  const [data, setData] = useState<PreviewPayload | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    const abort = new AbortController();

    request<PreviewPayload>(`${apiPath}/resources/${uriKey}/${String(id)}/preview`, { signal: abort.signal }).then(setData, () => {
      if (!abort.signal.aborted) {
        setFailed(true);
      }
    });

    return () => {
      abort.abort();
    };
  }, [apiPath, uriKey, id]);

  return (
    <Modal labelledBy={titleId} onClose={onClose} className={`${modalPanel} max-w-2xl`}>
      <div className="flex items-start justify-between gap-4">
        <div className="min-w-0">
          <h2 id={titleId} className="truncate text-base font-semibold">
            {data?.title ?? __('Loading…')}
          </h2>
          {data !== null && data.subtitle !== '' ? <p className="text-sm text-muted-foreground">{data.subtitle}</p> : null}
        </div>
        <button type="button" className={outlineButton} onClick={onClose}>
          {__('Close')}
        </button>
      </div>
      {failed ? <p className="text-sm text-destructive">{__('Something went wrong. Please try again.')}</p> : null}
      {data === null ? null : (
        <dl className="divide-y divide-border text-sm">
          {data.fields.map((field) => (
            <div key={field.key} className="grid gap-2 py-2 md:grid-cols-[minmax(0,1fr)_minmax(0,3fr)]">
              <dt className="font-medium text-muted-foreground">{field.label}</dt>
              <dd className="min-w-0 break-words">
                <DetailField field={field} />
              </dd>
            </div>
          ))}
        </dl>
      )}
      <div className="flex justify-end">
        <Link href={`${basePath}/resources/${uriKey}/${String(id)}`} className={primaryButton}>
          {__('View')}
        </Link>
      </div>
    </Modal>
  );
}
