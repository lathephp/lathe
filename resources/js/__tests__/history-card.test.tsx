import { __panel } from '@lathephp/core';
import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { HistoryCard } from '../records/HistoryCard';
import { sharedProps } from './support';

beforeEach(() => {
  __panel.sharedProps.set(sharedProps());
});

afterEach(() => {
  vi.restoreAllMocks();
});

function answer(events: unknown[]) {
  return vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(JSON.stringify({ events }), { status: 200 }));
}

const event = (id: number, title: string, at: string) => ({ id, type: 'update', title, user: 'Admin User', at });

describe('history card', () => {
  it('lists the newest events from the record history route, newest first, with who and when', async () => {
    const fetch = answer([event(2, 'Changed Status', '2026-10-17T21:00:00Z'), event(1, 'Created', '2026-10-17T09:00:00Z')]);

    render(<HistoryCard uriKey="orders" id={12} onShowAll={() => {}} />);

    const items = await screen.findAllByRole('listitem');
    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/resources/orders/12/history/latest');
    expect(items.map((item) => item.textContent)).toEqual(['Admin User Changed StatusOct 17, 2026, 21:00', 'Admin User CreatedOct 17, 2026, 09:00']);
    expect(screen.getByRole('heading', { name: 'History' })).toBeDefined();
  });

  it('says "No changes yet" for a record with no events', async () => {
    answer([]);

    render(<HistoryCard uriKey="orders" id={12} onShowAll={() => {}} />);

    expect(await screen.findByText('No changes yet')).toBeDefined();
    expect(screen.queryByRole('button', { name: 'Show all' })).toBeNull();
  });

  it('opens the full list from Show all', async () => {
    answer([event(1, 'Created', '2026-10-17T09:00:00Z')]);
    const onShowAll = vi.fn();

    render(<HistoryCard uriKey="orders" id={12} onShowAll={onShowAll} />);
    fireEvent.click(await screen.findByRole('button', { name: 'Show all' }));

    expect(onShowAll).toHaveBeenCalledOnce();
  });
});
