import { request } from '@lathephp/core';
import { useCallback, useEffect, useRef, useState } from 'react';

export interface NotificationAction {
  text: string;
  url: string;
  icon: string | null;
  style: 'link' | 'button' | 'icon';
  openInNewTab: boolean;
}

export interface NotificationData {
  id: string;
  icon: string | null;
  message: string;
  body: string | null;
  notificationType: 'success' | 'error' | 'warning' | 'info';
  actions: NotificationAction[];
  duration: number | null;
  readAt: string | null;
  createdAt: string | null;
  createdAtHuman: string;
}

interface ListResponse {
  notifications: NotificationData[];
  unreadCount: number;
}

/**
 * The unread count, polled while the tab is visible, and the list, loaded when the count
 * rises and when the panel is open. A rise only lights the bell, it never toasts.
 * The first count only seeds the baseline.
 */
export function useNotifications(basePath: string, pollingInterval: number, open: boolean) {
  const url = `${basePath}/api/notifications`;
  const [items, setItems] = useState<NotificationData[]>([]);
  const [unread, setUnread] = useState(0);
  const baseline = useRef<number | null>(null);
  const polling = useRef(false);

  const load = useCallback(async (): Promise<NotificationData[]> => {
    const data = await request<ListResponse>(url, { toastOnError: false });

    if (data === null) {
      return [];
    }

    setItems(data.notifications);
    setUnread(data.unreadCount);
    baseline.current = data.unreadCount;

    return data.notifications;
  }, [url]);

  const poll = useCallback(async () => {
    if (polling.current || document.visibilityState !== 'visible') {
      return;
    }

    polling.current = true;

    try {
      const data = await request<{ count: number }>(`${url}/count`, { toastOnError: false });
      const count = data?.count ?? 0;
      const before = baseline.current;

      setUnread(count);
      baseline.current = count;

      if (before !== null && count > before) {
        await load();
      }
    } catch {
      // The next tick tries again.
    } finally {
      polling.current = false;
    }
  }, [url, load]);

  useEffect(() => {
    // Timers, not a direct call: the first count must not set state inside the effect body.
    const first = setTimeout(() => {
      void poll();
    }, 0);
    const timer = setInterval(() => {
      void poll();
    }, pollingInterval * 1000);

    return () => {
      clearTimeout(first);
      clearInterval(timer);
    };
  }, [poll, pollingInterval]);

  useEffect(() => {
    if (!open) {
      return undefined;
    }

    const timer = setTimeout(() => {
      load().catch(() => undefined);
    }, 0);

    return () => {
      clearTimeout(timer);
    };
  }, [open, load]);

  // Each change reloads the list, which also moves the baseline so it never reads as a rise.
  const send = useCallback(
    async (path: string, method: string) => {
      try {
        await request(`${url}${path}`, { method });
        await load();
      } catch {
        // The list stays as it was.
      }
    },
    [url, load],
  );

  return {
    items,
    unread,
    markRead: (id: string) => send(`/${id}`, 'PUT'),
    markUnread: (id: string) => send(`/${id}/unread`, 'POST'),
    remove: (id: string) => send(`/${id}`, 'DELETE'),
    markAllRead: () => send('/mark-all-read', 'POST'),
    removeAll: () => send('', 'DELETE'),
  };
}
