import { queryClient, request, useLathe } from '@lathephp/core';

/** The query string that names one pivot row where a relation allows the same record twice. */
export function pivotQuery(pivotId: number | string | undefined): string {
  return pivotId === undefined ? '' : `?pivot_id=${encodeURIComponent(String(pivotId))}`;
}

/** Attach, update a pivot and detach on a pivot relation; each refetches the panel's rows once it lands. */
export function useRelationActions(uriKey: string, id: string | number, relationship: string) {
  const { apiPath } = useLathe();
  const base = `${apiPath}/resources/${uriKey}/${String(id)}/${relationship}`;

  async function send(url: string, method: string, body?: unknown) {
    await request(url, { method, body, toastOnError: false });
    await queryClient.invalidateQueries({ queryKey: ['relation', uriKey, String(id), relationship] });
  }

  return {
    base,
    attach: (relatedId: unknown, pivot: Record<string, unknown>) => send(base, 'POST', { related_id: relatedId, pivot }),
    updatePivot: (relatedId: number | string, pivotId: number | string | undefined, pivot: Record<string, unknown>) =>
      send(`${base}/${String(relatedId)}/pivot${pivotQuery(pivotId)}`, 'PUT', { pivot }),
    detach: (relatedId: number | string, pivotId: number | string | undefined) => send(`${base}/${String(relatedId)}${pivotQuery(pivotId)}`, 'DELETE'),
  };
}
