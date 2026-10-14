import { __panel } from '@lathephp/core';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { Toaster } from '../components/Toaster';
import { NotificationCenter } from '../notifications/NotificationCenter';
import { renderPage } from './support';

interface Action {
  text: string;
  url: string;
  icon: string | null;
  style: string;
  openInNewTab: boolean;
}

function item(id: string, message: string, extra: { actions?: Action[]; duration?: number | null; readAt?: string | null } = {}) {
  return {
    id,
    type: 'App\\Notifications\\Sent',
    icon: 'information-circle',
    message,
    body: `${message} body`,
    notificationType: 'info',
    actions: [] as Action[],
    duration: null as number | null,
    readAt: null as string | null,
    createdAt: '2026-10-14T10:00:00+00:00',
    createdAtHuman: '5 minutes ago',
    ...extra,
  };
}

let count = 0;
let list: ReturnType<typeof item>[] = [];
const calls: string[] = [];

function reply(body: unknown, status = 200) {
  return Promise.resolve(new Response(body === null ? null : JSON.stringify(body), { status }));
}

beforeEach(() => {
  count = 0;
  list = [];
  calls.length = 0;
  vi.useFakeTimers();
  vi.stubGlobal(
    'fetch',
    vi.fn((url: string, init?: RequestInit) => {
      const call = `${init?.method ?? 'GET'} ${url}`;
      calls.push(call);

      if (call === 'GET /lathe/api/notifications/count') {
        return reply({ count });
      }

      if (call === 'GET /lathe/api/notifications') {
        return reply({ notifications: list, unreadCount: list.filter((n) => n.readAt === null).length });
      }

      return reply(null, 204);
    }),
  );
});

afterEach(() => {
  vi.useRealTimers();
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

function mount(showUnreadCount = false) {
  return render(
    <>
      <NotificationCenter basePath="/lathe" pollingInterval={7} showUnreadCount={showUnreadCount} />
      <Toaster />
    </>,
  );
}

async function tick(seconds: number) {
  await act(async () => {
    await vi.advanceTimersByTimeAsync(seconds * 1000);
  });
}

async function settle() {
  await tick(0);
}

function bell(): HTMLElement {
  return screen.getByRole('button', { name: /Notifications/ });
}

async function openPanel() {
  fireEvent.keyDown(document.body, { key: 'n' });
  await settle();
}

// The bell and its panel load lazily; loading them once here keeps the fake timers from racing the import.
beforeAll(async () => {
  await import('../notifications/NotificationPanel');
  await import('../notifications/NotificationCenter');
});

describe('bell', () => {
  it('shows a red dot when unread exist', async () => {
    count = 3;
    mount();
    await settle();

    expect(bell().querySelector('[data-testid="unread-dot"]')).not.toBeNull();
    expect(bell().textContent).toBe('');
  });

  it('shows no dot at zero', async () => {
    mount();
    await settle();

    expect(bell().querySelector('[data-testid="unread-dot"]')).toBeNull();
  });

  it('shows the number with show_unread_count, and 99+ above 99', async () => {
    count = 100;
    mount(true);
    await settle();
    expect(bell().textContent).toBe('99+');

    count = 12;
    await tick(7);
    expect(bell().textContent).toBe('12');
  });
});

describe('polling', () => {
  it('polls the count every interval while visible and not the list', async () => {
    mount();
    await settle();
    await tick(7);
    await tick(7);

    expect(calls).toEqual([
      'GET /lathe/api/notifications/count',
      'GET /lathe/api/notifications/count',
      'GET /lathe/api/notifications/count',
    ]);
  });

  it('does not poll while the tab is hidden', async () => {
    const state = vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('hidden');
    mount();
    await tick(30);

    expect(calls).toEqual([]);
    state.mockReturnValue('visible');
    await tick(7);
    expect(calls).toEqual(['GET /lathe/api/notifications/count']);
  });

  it('loads the list when the count rises', async () => {
    mount();
    await settle();
    count = 1;
    list = [item('a', 'Export ready')];
    await tick(7);

    expect(calls).toEqual([
      'GET /lathe/api/notifications/count',
      'GET /lathe/api/notifications/count',
      'GET /lathe/api/notifications',
    ]);
  });
});

describe('panel', () => {
  it('toggles with n and loads the list on open', async () => {
    list = [item('a', 'Export ready')];
    count = 1;
    mount();
    await settle();

    expect(screen.queryByRole('dialog')).toBeNull();
    await openPanel();
    expect(screen.getByRole('dialog', { name: 'Notifications' })).toBeTruthy();
    expect(calls).toContain('GET /lathe/api/notifications');

    fireEvent.keyDown(document.body, { key: 'n' });
    expect(screen.queryByRole('dialog')).toBeNull();
  });

  it('lists message, body, relative time and action links', async () => {
    list = [
      item('a', 'Export ready', {
        actions: [
          { text: 'Download', url: '/lathe/exports/1', icon: null, style: 'button', openInNewTab: true },
          { text: 'Open', url: '/lathe/runs/1', icon: null, style: 'link', openInNewTab: false },
        ],
      }),
    ];
    count = 1;
    mount();
    await openPanel();
    const panel = within(screen.getByRole('dialog'));

    expect(panel.getByText('Export ready')).toBeTruthy();
    expect(panel.getByText('Export ready body')).toBeTruthy();
    expect(panel.getByText('5 minutes ago')).toBeTruthy();
    const download = panel.getByRole('link', { name: 'Download' });
    expect(download.getAttribute('href')).toBe('/lathe/exports/1');
    expect(download.getAttribute('target')).toBe('_blank');
    expect(panel.getByRole('link', { name: 'Open' }).getAttribute('target')).toBeNull();
  });

  it('marks read, marks unread and deletes through their endpoints', async () => {
    list = [item('a', 'Unread one'), item('b', 'Read one', { readAt: '2026-10-14T10:01:00+00:00' })];
    count = 1;
    mount();
    await openPanel();
    calls.length = 0;
    const panel = within(screen.getByRole('dialog'));

    fireEvent.click(panel.getByRole('button', { name: 'Mark as read' }));
    await settle();
    fireEvent.click(panel.getByRole('button', { name: 'Mark as unread' }));
    await settle();
    fireEvent.click(panel.getAllByRole('button', { name: 'Delete' })[0] as HTMLElement);
    await settle();

    expect(calls.filter((call) => !call.startsWith('GET'))).toEqual([
      'PUT /lathe/api/notifications/a',
      'POST /lathe/api/notifications/b/unread',
      'DELETE /lathe/api/notifications/a',
    ]);
  });

  it('disables Mark all read at zero unread and calls it otherwise', async () => {
    list = [item('b', 'Read one', { readAt: '2026-10-14T10:01:00+00:00' })];
    mount();
    await openPanel();

    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Mark all read' }).disabled).toBe(true);
    fireEvent.keyDown(document.body, { key: 'n' });

    list = [item('a', 'Unread one')];
    await openPanel();
    const markAll = screen.getByRole<HTMLButtonElement>('button', { name: 'Mark all read' });
    expect(markAll.disabled).toBe(false);
    fireEvent.click(markAll);
    await settle();

    expect(calls).toContain('POST /lathe/api/notifications/mark-all-read');
  });

  it('deletes all and shows the empty state', async () => {
    list = [item('a', 'One')];
    count = 1;
    mount();
    await openPanel();

    list = [];
    fireEvent.click(screen.getByRole('button', { name: 'Delete all' }));
    await settle();

    expect(calls).toContain('DELETE /lathe/api/notifications');
    expect(screen.getByText('No notifications')).toBeTruthy();
  });
});

describe('toast on a rise', () => {
  it('shows no toast on the first load', async () => {
    count = 4;
    list = [item('a', 'Old')];
    mount();
    await settle();

    expect(__panel.toasts.get()).toEqual([]);
  });

  it('toasts the newest unread item with its actions and duration', async () => {
    mount();
    await settle();
    count = 2;
    list = [
      item('b', 'Export ready', {
        duration: 9000,
        actions: [{ text: 'Download', url: '/lathe/exports/1', icon: null, style: 'button', openInNewTab: true }],
      }),
      item('a', 'Older'),
    ];
    await tick(7);

    expect(__panel.toasts.get()).toMatchObject([
      { message: 'Export ready', links: [{ label: 'Download', href: '/lathe/exports/1', newTab: true }] },
    ]);
    const link = screen.getByRole('link', { name: 'Download' });
    expect(link.getAttribute('href')).toBe('/lathe/exports/1');
    expect(link.getAttribute('target')).toBe('_blank');

    await tick(8.9);
    expect(__panel.toasts.get()).toHaveLength(1);
    await tick(0.2);
    expect(__panel.toasts.get()).toEqual([]);
  });

  it('shows no toast while the panel is open', async () => {
    mount();
    await settle();
    await openPanel();
    count = 1;
    list = [item('a', 'New')];
    await tick(7);

    expect(__panel.toasts.get()).toEqual([]);
  });
});

describe('notifications.enabled', () => {
  const dashboard = { dashboard: { uriKey: 'main', name: 'Dashboard', description: null, icon: null, showRefreshButton: false }, widgets: [] };

  it('mounts no bell and sends no request when off', async () => {
    renderPage('Dashboard', dashboard, { shared: { notifications: { enabled: false, pollingInterval: 7, showUnreadCount: false } } });
    await tick(30);

    expect(screen.queryByRole('button', { name: /Notifications/ })).toBeNull();
    expect(fetch).not.toHaveBeenCalled();
  });

  it('mounts the bell in the header when on', async () => {
    count = 1;
    renderPage('Dashboard', dashboard, { shared: { notifications: { enabled: true, pollingInterval: 7, showUnreadCount: true } } });
    await settle();
    await settle();

    expect(within(screen.getByRole('banner')).getByRole('button', { name: /Notifications/ }).textContent).toBe('1');
  });
});
