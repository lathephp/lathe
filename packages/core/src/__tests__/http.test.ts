import { afterEach, describe, expect, it, vi } from 'vitest';
import { __panel, ApiError, request } from '../index';

function respond(status: number, body: string): void {
  vi.stubGlobal(
    'fetch',
    vi.fn(() => Promise.resolve(new Response(status === 204 ? null : body, { status }))),
  );
}

afterEach(() => {
  vi.unstubAllGlobals();
});

describe('request', () => {
  it('sends the XSRF-TOKEN cookie as X-XSRF-TOKEN', async () => {
    vi.stubGlobal('document', { cookie: 'other=1; XSRF-TOKEN=abc%3D%3D' });
    respond(200, '{"ok":true}');

    await request('/lathe/api/thing', { method: 'POST', body: { a: 1 } });

    const init = vi.mocked(fetch).mock.calls[0]?.[1];
    expect(init?.headers).toMatchObject({ 'X-XSRF-TOKEN': 'abc==', Accept: 'application/json' });
  });

  it('answers null for a 204', async () => {
    respond(204, '');

    expect(await request('/x')).toBeNull();
  });

  it('turns a failure into an ApiError with status and data', async () => {
    respond(422, '{"errors":{"email":["Wrong"]}}');

    const error = await request('/x').catch((e: unknown) => e);

    expect(error).toBeInstanceOf(ApiError);
    expect(error).toMatchObject({ status: 422, data: { errors: { email: ['Wrong'] } } });
  });

  it('turns a malformed body into an ApiError, never a SyntaxError', async () => {
    respond(200, '<html>oops');

    const error = await request('/x').catch((e: unknown) => e);

    expect(error).toBeInstanceOf(ApiError);
    expect(error).not.toBeInstanceOf(SyntaxError);
  });
});

describe('request on a server error', () => {
  afterEach(() => {
    __panel.toasts.set([]);
  });

  it('toasts a 5xx with a Retry that sends the request again', async () => {
    respond(500, '{"message":"Server Error"}');

    await request('/lathe/api/thing').catch(() => undefined);

    const shown = __panel.toasts.get();
    expect(shown.map(({ type, message, action }) => ({ type, message, label: action?.label }))).toEqual([
      { type: 'error', message: 'Something went wrong. Please try again.', label: 'Retry' },
    ]);

    shown[0]?.action?.onClick();

    expect(vi.mocked(fetch).mock.calls.map(([url]) => url)).toEqual(['/lathe/api/thing', '/lathe/api/thing']);
  });

  it('pins: shows no toast to a caller that asks for none', async () => {
    respond(503, '');

    await request('/x', { toastOnError: false }).catch(() => undefined);

    expect(__panel.toasts.get()).toEqual([]);
  });

  it('pins: shows no toast for a 4xx', async () => {
    respond(404, '{}');

    await request('/x').catch(() => undefined);

    expect(__panel.toasts.get()).toEqual([]);
  });
});

describe('request for a file', () => {
  it('answers the body as a Blob', async () => {
    respond(200, 'id,name\n1,Ada\n');

    const file = await request<Blob>('/lathe/api/export', { responseType: 'blob' });

    expect(file).toBeInstanceOf(Blob);
    expect(await file?.text()).toBe('id,name\n1,Ada\n');
  });
});
