import { __panel, ApiError, csrfHeaders, request, toast, useLathe, useTranslation } from '@lathephp/core';
import { useContext, useEffect, useRef, useState, useSyncExternalStore } from 'react';
import type { FormFieldData } from '../types';
import { FormUploadsContext } from './uploads';

const MB = 1024 * 1024;

/** A chunk request that fails is sent this many more times before the upload stops. */
const RETRIES = 3;

/** The types a canvas can encode again; any other image uploads as it is. */
const RESIZABLE = new Set(['image/png', 'image/jpeg', 'image/webp']);

/** An upload in progress, kept in session storage so a reload can resume it. */
interface SavedUpload {
  upload_id: string;
  name: string;
  size: number;
  lastModified: number;
  attribute: string;
  chunk_size: number;
}

function storageKey(attribute: string): string {
  return `lathe-upload:${window.location.pathname}:${attribute}`;
}

function readSaved(attribute: string, file: File): SavedUpload | null {
  try {
    const raw = window.sessionStorage.getItem(storageKey(attribute));
    const saved = raw === null ? null : (JSON.parse(raw) as SavedUpload);

    return saved !== null && saved.name === file.name && saved.size === file.size && saved.lastModified === file.lastModified ? saved : null;
  } catch {
    return null;
  }
}

function writeSaved(saved: SavedUpload | null, attribute: string): void {
  try {
    if (saved === null) {
      window.sessionStorage.removeItem(storageKey(attribute));
    } else {
      window.sessionStorage.setItem(storageKey(attribute), JSON.stringify(saved));
    }
  } catch {
    // Without session storage an upload still works; it only cannot resume after a reload.
  }
}

/** The same test the server's FileField::accepts() runs: a MIME pattern, or an extension. */
export function accepts(types: string[], name: string, mime: string): boolean {
  if (types.length === 0) {
    return true;
  }

  const extension = name.includes('.') ? (name.split('.').pop() ?? '').toLowerCase() : '';

  return types.some((type) => {
    const pattern = type.toLowerCase();

    if (pattern.includes('/')) {
      const [major, minor] = pattern.split('/');
      const [fileMajor, fileMinor] = mime.toLowerCase().split('/');

      return major === fileMajor && (minor === '*' || minor === fileMinor);
    }

    return extension !== '' && pattern.replace(/^\./, '') === extension;
  });
}

/** Shrink an image wider than maxWidth in the browser, so the server needs no image library. */
async function downsize(file: File, maxWidth: unknown): Promise<Blob> {
  if (typeof maxWidth !== 'number' || !RESIZABLE.has(file.type)) {
    return file;
  }

  try {
    const bitmap = await createImageBitmap(file);

    if (bitmap.width <= maxWidth) {
      bitmap.close();

      return file;
    }

    const height = Math.round((bitmap.height * maxWidth) / bitmap.width);
    const canvas = new OffscreenCanvas(maxWidth, height);
    canvas.getContext('2d')?.drawImage(bitmap, 0, 0, maxWidth, height);
    bitmap.close();

    return await canvas.convertToBlob({ type: file.type });
  } catch {
    // An image the browser cannot decode uploads as it is; the server sniffs its content.
    return file;
  }
}

/** One upload as it runs: whether it still counts, as a newer pick replaces an older one, and the signal that stops its requests. */
interface Run {
  live: () => boolean;
  signal: AbortSignal;
}

async function putChunk(url: string, body: Blob, signal: AbortSignal): Promise<void> {
  for (let attempt = 0; ; attempt++) {
    let status = 0;

    try {
      const response = await fetch(url, {
        method: 'PUT',
        headers: { Accept: 'application/json', 'Content-Type': 'application/octet-stream', ...csrfHeaders() },
        body,
        credentials: 'same-origin',
        signal,
      });

      if (response.ok) {
        return;
      }

      status = response.status;
    } catch {
      // A network failure is retried like a server error.
    }

    // A replaced upload stops at once, with no retry.
    if (status === 413 || attempt === RETRIES || signal.aborted) {
      throw new ApiError(status, null);
    }

    await new Promise((resolve) => setTimeout(resolve, 1000 * (attempt + 1)));
  }
}

/** The first message of a 422 body, such as the type or size the server refused. */
function firstError(data: unknown): string | undefined {
  const errors = (data as { errors?: Record<string, string[]> } | null)?.errors ?? {};

  return Object.values(errors)[0]?.[0];
}

function numberOr(value: unknown, fallback: number): number {
  return typeof value === 'number' ? value : fallback;
}

/**
 * Upload a chosen file in chunks to the field's start route, resuming an upload this page
 * started before a reload. Leaving the page never cancels: the pending upload stays for 24 hours.
 */
export function useChunkedUpload(
  field: FormFieldData,
  attribute: string,
  onUploaded: (uploadId: string) => void,
  /** More fields for the start request's body, read when an upload starts, such as an editor's draft_id. */
  startBody: () => Record<string, unknown> = () => ({}),
) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const page = useSyncExternalStore(__panel.sharedProps.subscribe, __panel.sharedProps.get);
  const [progress, setProgress] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [name, setName] = useState<string | null>(null);
  const limit = numberOr(field.maxSize, 10);
  const tooLarge = __('File exceeds the :size MB limit', { size: limit });
  const form = useContext(FormUploadsContext);
  // The ends of this field's running uploads: a field that unmounts mid-upload, such as a removed
  // repeater row, must not leave its form's Save held.
  const ends = useRef(new Set<() => void>());

  useEffect(() => {
    const running = ends.current;

    return () => {
      running.forEach((end) => {
        end();
      });
      running.clear();
    };
  }, []);

  /** Runs one upload with the form counting it, from the pick until it ends, either way. */
  async function tracked<T>(work: () => Promise<T>): Promise<T> {
    const end = form?.begin() ?? (() => undefined);
    ends.current.add(end);

    try {
      return await work();
    } finally {
      ends.current.delete(end);
      end();
    }
  }

  async function begin(file: File, blob: Blob, run: Run): Promise<{ id: string; chunkSize: number; received: number[] }> {
    const saved = readSaved(attribute, file);

    if (saved !== null) {
      try {
        const status = await request<{ size: number; received: number[] }>(`${apiPath}/uploads/${saved.upload_id}`, { toastOnError: false, signal: run.signal });

        if (status !== null && status.size === blob.size) {
          return { id: saved.upload_id, chunkSize: saved.chunk_size, received: status.received };
        }
      } catch {
        // The upload expired or was used: start a new one.
      }
    }

    const resource = (page.resource as { uriKey?: string } | undefined)?.uriKey ?? '';
    const id = page.mode === 'update' ? page.id : undefined;
    const started = await request<{ upload_id: string; chunk_size: number }>(`${apiPath}/resources/${resource}/uploads/${attribute}`, {
      method: 'POST',
      toastOnError: false,
      signal: run.signal,
      body: { name: file.name, size: blob.size, mime: blob.type || file.type, ...(id === undefined || id === null ? {} : { resource_id: id }), ...startBody() },
    });

    if (started === null || !run.live()) {
      throw new ApiError(0, null);
    }

    writeSaved({ upload_id: started.upload_id, name: file.name, size: file.size, lastModified: file.lastModified, attribute, chunk_size: started.chunk_size }, attribute);

    return { id: started.upload_id, chunkSize: started.chunk_size, received: [] };
  }

  /**
   * Sends the file and answers its upload id, or null when it failed or a newer pick replaced it.
   * A replaced upload touches nothing: no progress, no error, and not the newer one's resume entry.
   */
  async function send(file: File, blob: Blob, run: Run, retry?: () => void): Promise<string | null> {
    try {
      const { id, chunkSize, received } = await begin(file, blob, run);
      const total = Math.max(1, Math.ceil(blob.size / chunkSize));
      const sizeOf = (index: number) => Math.min(chunkSize, blob.size - index * chunkSize);
      // The bar stops at 99 until the server has assembled the file and the form holds its id.
      const percent = (bytes: number) => Math.min(99, Math.floor((bytes / blob.size) * 100));
      let sent = received.reduce((sum, index) => sum + sizeOf(index), 0);
      setProgress(percent(sent));

      for (let index = 0; index < total; index++) {
        if (received.includes(index)) {
          continue;
        }

        await putChunk(`${apiPath}/uploads/${id}/chunks/${String(index)}`, blob.slice(index * chunkSize, (index + 1) * chunkSize), run.signal);

        if (!run.live()) {
          return null;
        }

        sent += sizeOf(index);
        setProgress(percent(sent));
      }

      await request(`${apiPath}/uploads/${id}/complete`, { method: 'POST', toastOnError: false, signal: run.signal });

      if (!run.live()) {
        return null;
      }

      writeSaved(null, attribute);
      setProgress(100);

      return id;
    } catch (failure) {
      if (!run.live()) {
        return null;
      }

      setProgress(null);

      if (failure instanceof ApiError && failure.status === 413) {
        setError(tooLarge);
      } else if (failure instanceof ApiError && failure.status === 422) {
        writeSaved(null, attribute);
        setError(firstError(failure.data) ?? __('The upload failed.'));
      } else {
        toast.error(__('The upload failed.'), retry === undefined ? {} : { action: { label: __('Retry'), onClick: retry } });
      }

      return null;
    }
  }

  /** Whether the file may upload; one over the limit or of a type the field refuses shows why. */
  function allowed(file: File): boolean {
    const types = Array.isArray(field.acceptedTypes) ? field.acceptedTypes.filter((type): type is string => typeof type === 'string') : [];
    // A file over the limit says so whatever its type, unless the browser shrinks it below.
    const shrinks = typeof field.maxWidth === 'number' && RESIZABLE.has(file.type);

    if (!shrinks && file.size > limit * MB) {
      setError(tooLarge);

      return false;
    }

    if (!accepts(types, file.name, file.type)) {
      setError(__('The file type is not one of: :types.', { types: types.join(', ') }));

      return false;
    }

    return true;
  }

  /** Shrinks the image the field asks to, then sends it; answers the upload id or null. */
  async function upload(file: File, run: Run, retry?: (blob: Blob) => void): Promise<string | null> {
    const blob = await downsize(file, field.maxWidth);

    if (!run.live()) {
      return null;
    }

    if (blob.size > limit * MB) {
      setError(tooLarge);

      return null;
    }

    return send(file, blob, run, retry === undefined ? undefined : () => {
      retry(blob);
    });
  }

  // The field's pick being uploaded; a new pick stops it and takes its place.
  const latest = useRef<AbortController | null>(null);

  function replace(): Run {
    latest.current?.abort();
    const controller = new AbortController();
    latest.current = controller;

    return { live: () => latest.current === controller, signal: controller.signal };
  }

  /** Sends the field's file again from a failed upload's Retry, as the field's newest pick. */
  async function resend(file: File, blob: Blob): Promise<void> {
    const run = replace();
    const id = await tracked(() => send(file, blob, run, () => void resend(file, blob)));

    if (id !== null && run.live()) {
      onUploaded(id);
    }
  }

  /** The field's pick: it replaces an upload still running, so the field holds the last file chosen. */
  async function choose(file: File): Promise<void> {
    const run = replace();
    setError(null);
    setProgress(null);
    setName(file.name);

    if (!allowed(file)) {
      return;
    }

    const id = await tracked(() => upload(file, run, (blob) => void resend(file, blob)));

    if (id !== null && run.live()) {
      onUploaded(id);
    }
  }

  /** An upload of its own beside any others, as for each image pasted into an editor: answers its upload id, or null. */
  async function add(file: File): Promise<string | null> {
    setError(null);

    if (!allowed(file)) {
      return null;
    }

    return tracked(() => upload(file, { live: () => true, signal: new AbortController().signal }));
  }

  return { progress, error, name, choose, add };
}
