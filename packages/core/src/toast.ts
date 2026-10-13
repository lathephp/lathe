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

function show(type: ToastType, message: string): void {
  toasts.set([...toasts.get(), { id: nextId++, type, message }]);
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
