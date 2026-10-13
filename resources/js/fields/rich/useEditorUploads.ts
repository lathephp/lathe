import { __panel, request, useLathe } from '@lathephp/core';
import { useEffect, useRef, useSyncExternalStore } from 'react';
import type { FormFieldData } from '../types';
import { useChunkedUpload } from '../upload/useChunkedUpload';
import { forgetDraftId, setDraftId } from './drafts';

/**
 * Image uploads for an editor with files. The first upload asks the server for a draft id;
 * each image goes through the chunked protocol under it, then is stored on the field's disk,
 * and its URL comes back for the editor to insert. The save attaches the draft's images.
 */
export function useEditorUploads(field: FormFieldData) {
  const { apiPath } = useLathe();
  const page = useSyncExternalStore(__panel.sharedProps.subscribe, __panel.sharedProps.get);
  const resource = (page.resource as { uriKey?: string } | undefined)?.uriKey ?? '';
  const recordId = page.mode === 'update' && (typeof page.id === 'string' || typeof page.id === 'number') ? String(page.id) : null;
  const draft = useRef<string | null>(null);
  const waiting = useRef<((uploadId: string | null) => void) | null>(null);
  const upload = useChunkedUpload(
    field,
    field.attribute,
    (uploadId) => waiting.current?.(uploadId),
    () => ({ draft_id: draft.current }),
  );
  const base = `${apiPath}/resources/${resource}/field-attachment/${encodeURIComponent(field.attribute)}`;

  // A form opened later must not send this editor's draft.
  useEffect(() => () => {
    forgetDraftId(resource, field.attribute);
  }, [resource, field.attribute]);

  async function draftId(): Promise<string> {
    if (draft.current === null) {
      const result = await request<{ draft_id: string }>(`${base}/draft-id${recordId === null ? '' : `?resource_id=${encodeURIComponent(recordId)}`}`);

      if (result === null) {
        throw new Error('No draft id.');
      }

      draft.current = result.draft_id;
      setDraftId(resource, field.attribute, result.draft_id);
    }

    return draft.current;
  }

  async function uploadImage(file: File): Promise<string> {
    await draftId();

    // choose() reports the upload id through onUploaded and settles once the upload ends either way.
    const uploadId = await new Promise<string | null>((resolve) => {
      waiting.current = resolve;
      void upload.choose(file).then(() => {
        resolve(null);
      });
    });
    waiting.current = null;

    if (uploadId === null) {
      throw new Error('The upload failed.');
    }

    const attached = await request<{ url: string }>(base, { method: 'POST', body: { upload_id: uploadId, ...(recordId === null ? {} : { resource_id: recordId }) } });

    if (attached === null) {
      throw new Error('The upload failed.');
    }

    return attached.url;
  }

  return { uploadImage, error: upload.error };
}
