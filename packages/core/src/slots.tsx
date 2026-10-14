import { createElement, Suspense, type ReactNode } from 'react';
import type { ComponentOrLazy } from './registry';

/** What every replacement receives besides the original's props. slotVersion changes only with a major release. */
export interface SlotProps {
  slotVersion: number;
}

const slotVersion = 1;
const slots = new Map<string, ComponentOrLazy<SlotProps>>();

// A field, filter, widget or action modal part is named by its kind and component key.
const known = /^(shell\.(header|sidebar|footer)|auth\.login|(field\.(index|detail|form)|filter|widget|action-modal):[\w-]+)$/;

/** Swap a built-in part of the panel, such as registry.replace('shell.footer', Footer). */
function replace(key: string, component: ComponentOrLazy<SlotProps>): void {
  if (!known.test(key)) {
    const message = `[lathe] registry.replace() has no part named "${key}".`;

    if (import.meta.env.DEV) {
      throw new Error(message);
    }

    console.warn(message);

    return;
  }

  slots.set(key, component);
}

export const registry = { replace };

/** The replacement registered for a part, given the original's props, or the original itself. */
export function Slot({ name, children, ...props }: { name: string; children?: ReactNode } & Record<string, unknown>) {
  const Replacement = slots.get(name);

  return Replacement === undefined ? children : <Suspense fallback={null}>{createElement(Replacement, { ...props, slotVersion })}</Suspense>;
}
