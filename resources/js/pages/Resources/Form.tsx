import { Head, Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { useEffect, useRef } from 'react';
import type { FormProps } from '../../fields/types';
import { ConflictDialog } from '../../records/ConflictDialog';
import { ErrorSummary } from '../../records/ErrorSummary';
import { FormFields } from '../../records/FormFields';
import { ReauthModal } from '../../records/ReauthModal';
import { useLeaveGuard } from '../../records/useLeaveGuard';
import { useRecordForm, type SubmitIntent } from '../../records/useRecordForm';
import { PageHeader } from '../../layout/PageHeader';
import type { SharedProps } from '../../types';

/** The create and edit form of a Resource. */
export default function Form({ resource, mode, id, title, form, submitLabel }: FormProps) {
  const { __ } = useTranslation();
  const page = usePage<SharedProps>();
  const { basePath, auth } = page.props;
  const formRef = useRef<HTMLFormElement>(null);
  const summaryRef = useRef<HTMLDivElement>(null);
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
  useLeaveGuard(record.isDirty);
  const updating = mode === 'update';
  const cancelHref = updating ? `${basePath}/resources/${resource.uriKey}/${String(id)}` : `${basePath}/resources/${resource.uriKey}`;

  // A failed save brings the summary into view, then focuses the first field it marked.
  // Keyed on the save count, so fixing one field does not pull focus away from the next.
  useEffect(() => {
    if (record.failures > 0) {
      summaryRef.current?.scrollIntoView({ block: 'start' });
      formRef.current?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus({ preventScroll: true });
    }
  }, [record.failures]);

  function submit(intent: SubmitIntent) {
    void record.submit(intent);
  }

  return (
    <div className="flex flex-1 flex-col">
      <Head title={title} />
      <div className="mx-auto w-full max-w-[880px]">
        <PageHeader title={title} />
      </div>
      <form
        ref={formRef}
        data-lathe-form
        noValidate
        className="mt-5 flex flex-1 flex-col"
        onSubmit={(event) => {
          event.preventDefault();
          submit('default');
        }}
      >
        <div className="mx-auto mb-6 w-full max-w-[880px] space-y-5">
          <ErrorSummary ref={summaryRef} errors={record.errors} fields={record.payload.fields} idPrefix="field" />
          <FormFields uriKey={resource.uriKey} mode={mode} id={id} record={record} />
        </div>
        {/* The window scrolls the shell, so the bar stays at the bottom of the viewport while the form does. */}
        <div data-lathe-form-footer className="lathe-form-footer sticky bottom-0 z-10">
          <div>
            {record.dirty ? (
              <span className="lathe-form-status">
                <span aria-hidden="true" />
                {__('Unsaved changes')}
              </span>
            ) : null}
            {record.draft.restored ? (
              <button type="button" className="lathe-button lathe-button-ghost lathe-button-size-default" onClick={record.draft.discard}>
                {__('Discard draft')}
              </button>
            ) : null}
            <Link href={cancelHref} className="lathe-button lathe-button-ghost lathe-button-size-default">
              {__('Cancel')}
            </Link>
            <button
              type="button"
              disabled={record.busy}
              className="lathe-button lathe-button-outline lathe-button-size-default"
              onClick={() => {
                submit(updating ? 'continue' : 'another');
              }}
            >
              {updating ? __('Update & Continue Editing') : __('Create and add another')}
            </button>
            <button type="submit" disabled={record.busy} className="lathe-button lathe-button-default lathe-button-size-default">
              {submitLabel}
            </button>
          </div>
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
