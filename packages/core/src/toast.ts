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
  /** How long it stays, in milliseconds; 4,000 when not given. */
  duration?: number;
}

let nextId = 1;

/** The toasts on screen; the panel's toaster renders them. */
export const toasts = createStore<ToastMessage[]>([]);

/** How long a toast stays, in milliseconds. */
const lifetime = 4_000;

/** At most this many show at once; a new one pushes out the oldest. */
const visible = 3;

function show(type: ToastType, message: string, options: ToastOptions = {}): void {
  const id = nextId++;
  const next = { id, type, message, ...(options.action === undefined ? {} : { action: options.action }) };
  toasts.set([...toasts.get(), next].slice(-visible));
  setTimeout(() => {
    dismissToast(id);
  }, options.duration ?? lifetime);
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
