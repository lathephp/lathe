import { useEffect, useRef } from 'react';
import { sharedProps } from './props';

type Handler = (event: KeyboardEvent) => void;

interface Shortcut {
  /** The written combination, such as `mod+k` or `g d`. */
  keys: string;
  /** Each step of a sequence: its key and whether it needs the mod key. */
  steps: { key: string; mod: boolean }[];
  handler: Handler;
}

/** A sequence such as `g d` must complete within this many milliseconds. */
export const SEQUENCE_TIMEOUT = 1000;

const shortcuts = new Set<Shortcut>();
const disabled = new Set<string>();
let pauses = 0;
let listening = false;
/** The keys pressed so far in a sequence, and when the last one was. */
let pending: { keys: string[]; at: number } = { keys: [], at: 0 };

function isMac(): boolean {
  return /mac/i.test(navigator.platform);
}

// A single key must not fire while the person types.
function isTyping(target: EventTarget | null): boolean {
  if (!(target instanceof Element)) {
    return false;
  }

  if (/^(input|textarea|select)$/i.test(target.tagName)) {
    return true;
  }

  const editable = target.closest('[contenteditable]');

  return editable !== null && editable.getAttribute('contenteditable') !== 'false';
}

function normalise(keys: string): string {
  return keys.trim().toLowerCase().split(/\s+/).join(' ');
}

/** The panel's keyboard_shortcuts.enabled; absent means on. */
function enabled(): boolean {
  return sharedProps.get().keyboardShortcuts !== false;
}

function onKeyDown(event: KeyboardEvent): void {
  if (pauses > 0 || !enabled()) {
    return;
  }

  const mac = isMac();
  const key = event.key.toLowerCase();
  const mod = mac ? event.metaKey : event.ctrlKey;
  const now = Date.now();
  // A plain key never fires while the person types or holds another modifier; a mod combination does.
  const blocked = !mod && (event.ctrlKey || event.metaKey || event.altKey || isTyping(event.target));
  const history = now - pending.at <= SEQUENCE_TIMEOUT ? pending.keys : [];
  const step = mod ? `mod+${key}` : key;
  let partial = false;

  if (blocked) {
    pending = { keys: [], at: 0 };

    return;
  }

  for (const shortcut of [...shortcuts]) {
    if (disabled.has(shortcut.keys)) {
      continue;
    }

    const steps = shortcut.steps.map((part) => (part.mod ? `mod+${part.key}` : part.key));
    const before = steps.slice(0, -1);
    const recent = before.length === 0 ? [] : history.slice(-before.length);

    if (steps.at(-1) === step && recent.join(' ') === before.join(' ')) {
      if (mod) {
        // The browser binds Ctrl+K, so a mod shortcut takes the key over.
        event.preventDefault();
      }

      pending = { keys: [], at: 0 };
      shortcut.handler(event);

      return;
    }

    if (steps.length > 1 && steps.slice(0, history.length + 1).join(' ') === [...history, step].join(' ')) {
      partial = true;
    }
  }

  pending = partial ? { keys: [...history, step], at: now } : { keys: [], at: 0 };
}

/** Run a handler on a key combination, such as `mod+k`, `/` or the sequence `g d`. Returns the function that removes it. */
export function addShortcut(keys: string, handler: Handler): () => void {
  const written = normalise(keys);
  const steps = written.split(' ').map((part) => {
    const pieces = part.split('+');

    return { key: pieces.at(-1) ?? '', mod: pieces.includes('mod') };
  });
  const shortcut: Shortcut = { keys: written, steps, handler };

  shortcuts.add(shortcut);

  if (!listening) {
    document.addEventListener('keydown', onKeyDown);
    listening = true;
  }

  return () => {
    shortcuts.delete(shortcut);
  };
}

/** Turn one combination off, a built-in included, until enableShortcut(). */
export function disableShortcut(keys: string): void {
  disabled.add(normalise(keys));
}

export function enableShortcut(keys: string): void {
  disabled.delete(normalise(keys));
}

/** Whether the panel runs shortcuts at all (keyboard_shortcuts.enabled). */
export function shortcutsEnabled(): boolean {
  return enabled();
}

/** Stop every shortcut until each pause has been resumed. Pauses add up. */
export function pauseShortcuts(): void {
  pauses += 1;
}

/** Undo one pause. Shortcuts fire again at zero. */
export function resumeShortcuts(): void {
  pauses = Math.max(0, pauses - 1);
}

/** How many pauses are open. For tests. */
export function shortcutPauseCount(): number {
  return pauses;
}

/** Run a handler on a combination while the component is mounted and `active` holds. */
export function useShortcut(keys: string, handler: Handler, active = true): void {
  const latest = useRef(handler);

  useEffect(() => {
    latest.current = handler;
  }, [handler]);

  useEffect(() => {
    if (!active) {
      return undefined;
    }

    return addShortcut(keys, (event) => {
      latest.current(event);
    });
  }, [keys, active]);
}

/** Pause every shortcut while the component is mounted and `active` holds, as a modal does. */
export function usePauseShortcuts(active = true): void {
  useEffect(() => {
    if (!active) {
      return undefined;
    }

    pauseShortcuts();

    return resumeShortcuts;
  }, [active]);
}
