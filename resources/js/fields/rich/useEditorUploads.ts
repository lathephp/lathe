import { __panel, request, useLathe } from '@lathephp/core';
import { useContext, useEffect, useRef, useSyncExternalStore } from 'react';
import type { FormFieldData } from '../types';
import { useChunkedUpload } from '../upload/useChunkedUpload';
import { FormUploadsContext } from '../upload/uploads';
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
  // One draft id for the editor, asked once even for two images pasted before it came back.
  const asking = useRef<Promise<string> | null>(null);
  const form = useContext(FormUploadsContext);
  const upload = useChunkedUpload(
    field,
    field.attribute,
    () => undefined,
    () => ({ draft_id: draft.current }),
  );
  const base = `${apiPath}/resources/${resource}/field-attachment/${encodeURIComponent(field.attribute)}`;

  // A form opened later must not send this editor's draft.
  useEffect(() => () => {
    forgetDraftId(resource, field.attribute);
  }, [resource, field.attribute]);

  function draftId(): Promise<string> {
    asking.current ??= request<{ draft_id: string }>(`${base}/draft-id${recordId === null ? '' : `?resource_id=${encodeURIComponent(recordId)}`}`).then(
      (result) => {
        if (result === null) {
          throw new Error('No draft id.');
        }

        draft.current = result.draft_id;
        setDraftId(resource, field.attribute, result.draft_id);

        return result.draft_id;
      },
      (failure: unknown) => {
        // A later image asks again.
        asking.current = null;
        throw failure;
      },
    );

    return asking.current;
  }

  /** Each image is an upload of its own, so two pasted one after the other each get their own URL. */
  async function uploadImage(file: File): Promise<string> {
    // The form holds its Save until the image is attached, not only uploaded.
    const end = form?.begin() ?? (() => undefined);

    try {
      await draftId();
      const uploadId = await upload.add(file);

      if (uploadId === null) {
        throw new Error('The upload failed.');
      }

      const attached = await request<{ url: string }>(base, { method: 'POST', body: { upload_id: uploadId, ...(recordId === null ? {} : { resource_id: recordId }) } });

      if (attached === null) {
        throw new Error('The upload failed.');
      }

      return attached.url;
    } finally {
      end();
    }
  }

  return { uploadImage, error: upload.error };
}
