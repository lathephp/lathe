type Handler = (event: KeyboardEvent) => void;

interface Shortcut {
  key: string;
  mod: boolean;
  handler: Handler;
}

const shortcuts = new Set<Shortcut>();
let pauses = 0;
let listening = false;

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

function onKeyDown(event: KeyboardEvent): void {
  if (pauses > 0) {
    return;
  }

  const mac = isMac();
  const key = event.key.toLowerCase();

  for (const shortcut of [...shortcuts]) {
    if (shortcut.key !== key) {
      continue;
    }

    if (shortcut.mod) {
      if (!(mac ? event.metaKey : event.ctrlKey)) {
        continue;
      }

      // The browser binds Ctrl+K, so a mod shortcut takes the key over.
      event.preventDefault();
    } else if (event.ctrlKey || event.metaKey || event.altKey || isTyping(event.target)) {
      continue;
    }

    shortcut.handler(event);
  }
}

/** Run a handler on a key combination, such as `mod+k` or `/`. Returns the function that removes it. */
export function addShortcut(keys: string, handler: Handler): () => void {
  const parts = keys.toLowerCase().split('+');
  const shortcut: Shortcut = { key: parts.at(-1) ?? '', mod: parts.includes('mod'), handler };

  shortcuts.add(shortcut);

  if (!listening) {
    document.addEventListener('keydown', onKeyDown);
    listening = true;
  }

  return () => {
    shortcuts.delete(shortcut);
  };
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
