import { createStore } from './store';

export type ToastType = 'success' | 'error' | 'info' | 'warning';

/** A button on a toast, such as Retry. */
export interface ToastAction {
  label: string;
  onClick: () => void;
}

export interface ToastMessage {
  id: number;
  type: ToastType;
  message: string;
  action?: ToastAction;
}

export interface ToastOptions {
  action?: ToastAction;
}

let nextId = 1;

/** The toasts on screen; the panel's toaster renders them. */
export const toasts = createStore<ToastMessage[]>([]);

/** How long a toast stays, in milliseconds; errors stay longer so they can be read. */
const lifetime: Record<ToastType, number> = { success: 5_000, info: 5_000, warning: 8_000, error: 8_000 };

function show(type: ToastType, message: string, options: ToastOptions = {}): void {
  const id = nextId++;
  toasts.set([...toasts.get(), { id, type, message, ...(options.action === undefined ? {} : { action: options.action }) }]);
  setTimeout(() => {
    dismissToast(id);
  }, lifetime[type]);
}

export function dismissToast(id: number): void {
  toasts.set(toasts.get().filter((toast) => toast.id !== id));
}

export const toast = {
  success: (message: string, options?: ToastOptions) => {
    show('success', message, options);
  },
  error: (message: string, options?: ToastOptions) => {
    show('error', message, options);
  },
  info: (message: string, options?: ToastOptions) => {
    show('info', message, options);
  },
  warning: (message: string, options?: ToastOptions) => {
    show('warning', message, options);
  },
};
