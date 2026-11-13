import { ApiError, request } from '@lathephp/core';
import { clearUndo } from '../index/undo';
import type { SelectionDescriptor } from '../index/useRowSelection';
import type { ActionRun } from './types';

function hasFile(fields: Record<string, unknown>): boolean {
  return Object.values(fields).some((value) => value instanceof File);
}

/** The multipart body of a form holding a file: the selection as a JSON string and each field under fields[...]. */
function multipart(action: string, selection: SelectionDescriptor, fields: Record<string, unknown>): FormData {
  const form = new FormData();
  form.append('action', action);
  form.append('selection', JSON.stringify(selection));

  for (const [key, value] of Object.entries(fields)) {
    if (value instanceof File) {
      form.append(`fields[${key}]`, value);
    } else if (value !== null && value !== undefined) {
      form.append(`fields[${key}]`, typeof value === 'string' ? value : JSON.stringify(value));
    }
  }

  return form;
}

/**
 * Runs an action on a selection. A form holding a file posts multipart, every other run posts
 * JSON; both go through the shared request, so a password check asks and sends the same body, file and all, again.
 */
export async function runAction(url: string, action: string, selection: SelectionDescriptor, fields: Record<string, unknown>): Promise<ActionRun> {
  // Ctrl+Z after an action must not undo an older edit of the records it changed.
  clearUndo();

  const body = hasFile(fields) ? multipart(action, selection, fields) : { action, selection, fields };
  const run = await request<ActionRun>(url, { method: 'POST', body, toastOnError: false });

  if (run === null) {
    throw new ApiError(204, null, 'The action answered nothing.');
  }

  return run;
}
