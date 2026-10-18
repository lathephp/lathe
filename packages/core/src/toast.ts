import { createStore } from './store';

export type ToastType = 'success' | 'error' | 'info' | 'warning';

/** A button on a toast, such as Retry. */
export interface ToastAction {
  label: string;
  onClick: () => void;
}

/** A link on a toast, such as Download. */
export interface ToastLink {
  label: string;
  href: string;
  newTab: boolean;
}

export interface ToastMessage {
  id: number;
  type: ToastType;
  message: string;
  action?: ToastAction;
  links?: ToastLink[];
  /** How long it stays, in milliseconds; 0 keeps it until it is dismissed. */
  timeout: number;
}

export interface ToastOptions {
  action?: ToastAction;
  links?: ToastLink[];
  /** How long it stays, in milliseconds; 5,000 when not given, and an error stays until dismissed. */
  duration?: number;
}

let nextId = 1;

/** The toasts on screen; the panel's toaster renders them. */
export const toasts = createStore<ToastMessage[]>([]);

/** How long a toast stays, in milliseconds. The panel's toaster runs the timer, so hover and focus pause it. */
const lifetime = 5_000;

/** At most this many show at once; a new one pushes out the oldest. */
const visible = 3;

function show(type: ToastType, message: string, options: ToastOptions = {}): void {
  const id = nextId++;
  const next: ToastMessage = {
    id,
    type,
    message,
    ...(options.action === undefined ? {} : { action: options.action }),
    ...(options.links === undefined ? {} : { links: options.links }),
    timeout: options.duration ?? (type === 'error' ? 0 : lifetime),
  };
  toasts.set([...toasts.get(), next].slice(-visible));
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
