import { ApiError, csrfHeaders, request } from '@lathephp/core';

const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

/**
 * Send an import file through the action's chunked upload route and answer its upload id.
 * The type follows the extension, since browsers name a csv file differently; the server
 * reads the content on complete either way.
 */
export async function uploadImportFile(startUrl: string, apiPath: string, file: File): Promise<string> {
  const mime = file.name.toLowerCase().endsWith('.xlsx') ? XLSX : 'text/csv';
  const started = await request<{ upload_id: string; chunk_size: number }>(startUrl, { method: 'POST', body: { name: file.name, size: file.size, mime }, toastOnError: false });

  if (started === null) {
    throw new ApiError(0, null);
  }

  const chunks = Math.max(1, Math.ceil(file.size / started.chunk_size));

  for (let index = 0; index < chunks; index++) {
    const response = await fetch(`${apiPath}/uploads/${started.upload_id}/chunks/${String(index)}`, {
      method: 'PUT',
      headers: { Accept: 'application/json', 'Content-Type': 'application/octet-stream', ...csrfHeaders() },
      body: file.slice(index * started.chunk_size, (index + 1) * started.chunk_size),
      credentials: 'same-origin',
    });

    if (!response.ok) {
      throw new ApiError(response.status, await response.json().catch(() => null));
    }
  }

  await request(`${apiPath}/uploads/${started.upload_id}/complete`, { method: 'POST', toastOnError: false });

  return started.upload_id;
}
