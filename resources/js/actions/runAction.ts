import { ApiError, csrfHeaders, request } from '@lathephp/core';
import type { SelectionDescriptor } from '../index/useRowSelection';
import type { ActionRun } from './types';

function hasFile(fields: Record<string, unknown>): boolean {
  return Object.values(fields).some((value) => value instanceof File);
}

/**
 * Runs an action on a selection. A form holding a file posts multipart, with the selection
 * as a JSON string and each field under fields[...]; every other run posts JSON.
 */
export async function runAction(url: string, action: string, selection: SelectionDescriptor, fields: Record<string, unknown>): Promise<ActionRun> {
  if (!hasFile(fields)) {
    const run = await request<ActionRun>(url, { method: 'POST', body: { action, selection, fields }, toastOnError: false });

    if (run === null) {
      throw new ApiError(204, null, 'The action answered nothing.');
    }

    return run;
  }

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

  let response: Response;

  try {
    response = await fetch(url, { method: 'POST', headers: { Accept: 'application/json', ...csrfHeaders() }, body: form, credentials: 'same-origin' });
  } catch (error) {
    throw new ApiError(0, null, error instanceof Error ? error.message : 'Network error');
  }

  const data: unknown = await response.json().catch(() => null);

  if (!response.ok) {
    throw new ApiError(response.status, data);
  }

  return data as ActionRun;
}
