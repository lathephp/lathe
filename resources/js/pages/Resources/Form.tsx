import { Head, Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { useEffect, useRef } from 'react';
import type { FormProps } from '../../fields/types';
import { Breadcrumbs } from '../../records/Breadcrumbs';
import { ConflictDialog } from '../../records/ConflictDialog';
import { FormFields } from '../../records/FormFields';
import { ReauthModal } from '../../records/ReauthModal';
import { useRecordForm, type SubmitIntent } from '../../records/useRecordForm';
import type { SharedProps } from '../../types';

/** The create and edit form of a Resource. */
export default function Form({ resource, mode, id, title, breadcrumbs, form, submitLabel }: FormProps) {
  const { __ } = useTranslation();
  const page = usePage<SharedProps>();
  const { basePath, auth } = page.props;
  const formRef = useRef<HTMLFormElement>(null);
  // A create opened from a relation panel: `orders/12/items` names the parent and the relation.
  const via = mode === 'create' ? new URLSearchParams(page.url.split('?')[1] ?? '').get('via') : null;
  const record = useRecordForm({ resource, mode, id, form, via }, (redirect) => {
    // Under a via the save goes back to the parent record, on the tab whose panel lists the new row.
    if (via === null) {
      router.visit(`${basePath}/resources/${redirect}`);

      return;
    }

    const [parentKey, parentId, relationship] = via.split('/');
    router.visit(`${basePath}/resources/${parentKey ?? ''}/${parentId ?? ''}?tab=${encodeURIComponent(relationship ?? '')}`);
  });
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
        data-lathe-form
        noValidate
        className="space-y-6"
        onSubmit={(event) => {
          event.preventDefault();
          submit('default');
        }}
      >
        <FormFields uriKey={resource.uriKey} mode={mode} id={id} record={record} />
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
