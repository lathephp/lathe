import { afterEach, describe, expect, it, vi } from 'vitest';
import { patchField, refusalOf } from '../index/inlineSave';
import { lastToken } from '../index/undo';

afterEach(() => {
  vi.restoreAllMocks();
});

const __ = (key: string) => key;
const reply = (status: number, body: unknown) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });

async function refusal(status: number, body: unknown): Promise<string> {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(reply(status, body));

  try {
    await patchField({ apiPath: '/lathe/api', uriKey: 'orders', rowId: 7, key: 'status' }, 'paid', 'token-1');
  } catch (failure) {
    return refusalOf(failure, __);
  }

  throw new Error('the patch did not fail');
}

describe('patching one field of a record', () => {
  it('sends the value and the stale token, and keeps the token the save returned for the next one', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(reply(200, { field: 'status', fields: { status: { value: 'paid', display: 'Paid' } }, updatedAt: 'token-2' }));

    const saved = await patchField({ apiPath: '/lathe/api', uriKey: 'orders', rowId: 7, key: 'status' }, 'paid', 'token-1');

    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/resources/orders/7/field/status');
    expect(fetch.mock.calls[0]?.[1]?.method).toBe('PATCH');
    expect(JSON.parse(fetch.mock.calls[0]?.[1]?.body as string)).toEqual({ value: 'paid', _updated_at: 'token-1' });
    expect(saved?.fields.status?.display).toBe('Paid');
    expect(lastToken('orders/7')).toBe('token-2');
  });

  it('gives the reason the server refused the value', async () => {
    expect(await refusal(422, { message: 'Invalid', errors: { value: ['The status may not be paid yet.'] } })).toBe('The status may not be paid yet.');
    expect(await refusal(403, { message: 'This action is unauthorized.' })).toBe('This action is unauthorized.');
    expect(await refusal(409, { error: 'stale', message: 'Stale', fields: {}, updatedAt: 'x' })).toBe('This record was changed by someone else since you opened it.');
    expect(await refusal(500, { message: 'Server Error' })).toBe('Something went wrong. Please try again.');
  });
});
