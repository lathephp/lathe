import { QueryClientProvider, queryClient } from '@lathephp/core';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { BelongsToIndex } from '../fields/relation/BelongsToField';
import type { FieldSchema } from '../fields/types';

afterEach(() => {
  queryClient.clear();
  vi.restoreAllMocks();
  vi.useRealTimers();
});

function renderOrders(peekable: boolean) {
  const field = { key: 'customer', label: 'Customer', component: 'belongs-to-field', resource: 'customers', peekable } as unknown as FieldSchema;
  const cell = { value: { value: 12, display: 'Ann Taylor', href: '/lathe/resources/customers/12' }, display: 'Ann Taylor' };

  return render(
    <QueryClientProvider client={queryClient}>
      <BelongsToIndex field={field} cell={cell} />
      <BelongsToIndex field={field} cell={cell} />
    </QueryClientProvider>,
  );
}

function answerPeek() {
  return vi.spyOn(globalThis, 'fetch').mockImplementation(() =>
    Promise.resolve(
      new Response(JSON.stringify({ id: 12, title: 'Ann Taylor', subtitle: 'ann.taylor@gmail.com', avatar: { url: null, initials: 'AT' }, fields: [{ key: 'email', label: 'Email', component: 'email-field', value: 'ann.taylor@gmail.com', display: 'ann.taylor@gmail.com' }] }), { status: 200 }),
    ),
  );
}

function link(index: number): HTMLElement {
  const found = screen.getAllByRole('link', { name: 'Ann Taylor' })[index];

  if (found === undefined) {
    throw new Error(`No link ${String(index)}.`);
  }

  return found;
}

async function hover(element: HTMLElement) {
  fireEvent.mouseEnter(element.parentElement ?? element);
  await act(async () => {
    await vi.advanceTimersByTimeAsync(400);
  });
}

describe('relation peek', () => {
  it('opens a card of the related record on hover, from one request, and repeats from the cache', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const fetch = answerPeek();
    renderOrders(true);

    const first = link(0);
    const second = link(1);
    await hover(first);
    await waitFor(() => {
      expect(screen.getByRole('tooltip').textContent).toContain('ann.taylor@gmail.com');
    });
    fireEvent.mouseLeave(first.parentElement ?? first);
    await hover(second);
    await waitFor(() => {
      expect(screen.getByRole('tooltip')).toBeTruthy();
    });

    const peeks = fetch.mock.calls.map(([input]) => (typeof input === 'string' ? input : '')).filter((url) => url.includes('/peek'));
    expect(peeks).toEqual(['/lathe/api/resources/customers/12/peek']);
  });

  it('opens nothing when the field does not peek', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const fetch = answerPeek();
    renderOrders(false);

    await hover(link(0));

    expect(screen.queryByRole('tooltip')).toBeNull();
    expect(fetch.mock.calls.filter(([input]) => typeof input === 'string' && input.includes('/peek'))).toEqual([]);
  });
});
