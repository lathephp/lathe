import { createStore } from './store';

export type ToastType = 'success' | 'error' | 'info' | 'warning';

export interface ToastMessage {
  id: number;
  type: ToastType;
  message: string;
}

let nextId = 1;

/** The toasts on screen; the panel's toaster renders them. */
export const toasts = createStore<ToastMessage[]>([]);

/** How long a toast stays, in milliseconds; errors stay longer so they can be read. */
const lifetime: Record<ToastType, number> = { success: 5_000, info: 5_000, warning: 8_000, error: 8_000 };

function show(type: ToastType, message: string): void {
  const id = nextId++;
  toasts.set([...toasts.get(), { id, type, message }]);
  setTimeout(() => {
    dismissToast(id);
  }, lifetime[type]);
}

export function dismissToast(id: number): void {
  toasts.set(toasts.get().filter((toast) => toast.id !== id));
}

export const toast = {
  success: (message: string) => {
    show('success', message);
  },
  error: (message: string) => {
    show('error', message);
  },
  info: (message: string) => {
    show('info', message);
  },
  warning: (message: string) => {
    show('warning', message);
  },
};
