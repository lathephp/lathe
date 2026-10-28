import { router } from '@inertiajs/react';
import { addShortcut } from '@lathephp/core';

// The cell edits made in this tab on this page, newest last. Ctrl/Cmd+Z and a toast's Undo take
// them back one at a time; nothing survives a reload, a visit to another page or an action run.
const stack: (() => void)[] = [];

// The token each record's last save from this tab returned, by "resource/id". An undo sends it,
// so a record someone else saved since opens the conflict dialog, even one a poll already showed.
const tokens = new Map<string, string | null | undefined>();

let bound = false;
let path = '';

export function lastToken(record: string): string | null | undefined {
  return tokens.get(record);
}

export function saved(record: string, token: string | null | undefined): void {
  tokens.set(record, token);
}

/** Adds an undo to the stack; the function it returns takes that undo, or nothing once it is gone. */
export function pushUndo(undo: () => void): () => void {
  if (!bound) {
    bound = true;
    path = window.location.pathname;
    addShortcut('mod+z', () => {
      stack.pop()?.();
    });
    // A partial reload or a poll keeps the page; a visit elsewhere ends its edits.
    router.on('navigate', () => {
      if (window.location.pathname !== path) {
        path = window.location.pathname;
        clearUndo();
      }
    });
  }

  stack.push(undo);

  return () => {
    const at = stack.indexOf(undo);

    if (at !== -1) {
      stack.splice(at, 1);
      undo();
    }
  };
}

/** An action, single or bulk, cannot be undone, and no older edit is undone after one. */
export function clearUndo(): void {
  stack.length = 0;
}
