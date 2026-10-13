// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { __panel, addShortcut, pauseShortcuts, resumeShortcuts } from '../index';

const removers: Array<() => void> = [];

function register(keys: string, handler: (event: KeyboardEvent) => void) {
  const remove = addShortcut(keys, handler);
  removers.push(remove);

  return remove;
}

function press(init: KeyboardEventInit, target: EventTarget = document.body) {
  const event = new KeyboardEvent('keydown', { bubbles: true, cancelable: true, ...init });
  target.dispatchEvent(event);

  return event;
}

function byId(id: string): HTMLElement {
  const element = document.getElementById(id);

  if (element === null) {
    throw new Error(`No element #${id}.`);
  }

  return element;
}

function setPlatform(platform: string) {
  vi.spyOn(navigator, 'platform', 'get').mockReturnValue(platform);
}

afterEach(() => {
  removers.splice(0).forEach((remove) => { remove(); });
  while (__panel.shortcutPauseCount() > 0) {
    resumeShortcuts();
  }
  vi.restoreAllMocks();
  document.body.innerHTML = '';
});

describe('shortcuts', () => {
  it('fires mod+k on Ctrl+K off a Mac and stops the browser binding', () => {
    setPlatform('Linux x86_64');
    const handler = vi.fn();
    register('mod+k', handler);

    expect(press({ key: 'k', ctrlKey: true }).defaultPrevented).toBe(true);
    press({ key: 'k', metaKey: true });
    press({ key: 'k' });

    expect(handler).toHaveBeenCalledTimes(1);
  });

  it('fires mod+k on Meta+K on a Mac', () => {
    setPlatform('MacIntel');
    const handler = vi.fn();
    register('mod+k', handler);

    press({ key: 'k', ctrlKey: true });
    press({ key: 'k', metaKey: true });

    expect(handler).toHaveBeenCalledTimes(1);
  });

  it('skips a single key while focus is in a field but not mod+k', () => {
    setPlatform('Linux x86_64');
    document.body.innerHTML =
      '<input id="i"><textarea id="t"></textarea><select id="s"></select><div id="c" contenteditable="true"></div><div id="p"></div>';
    const slash = vi.fn();
    const palette = vi.fn();
    register('/', slash);
    register('mod+k', palette);

    for (const id of ['i', 't', 's', 'c']) {
      const field = byId(id);
      press({ key: '/' }, field);
      press({ key: 'k', ctrlKey: true }, field);
    }

    expect(slash).not.toHaveBeenCalled();
    expect(palette).toHaveBeenCalledTimes(4);

    press({ key: '/' }, byId('p'));
    expect(slash).toHaveBeenCalledTimes(1);
  });

  it('fires only when every pause has been resumed', () => {
    const handler = vi.fn();
    register('/', handler);

    pauseShortcuts();
    pauseShortcuts();
    resumeShortcuts();
    press({ key: '/' });

    expect(handler).not.toHaveBeenCalled();
    expect(__panel.shortcutPauseCount()).toBe(1);

    resumeShortcuts();
    press({ key: '/' });

    expect(handler).toHaveBeenCalledTimes(1);
  });

  it('stops a shortcut once its remover has run', () => {
    const handler = vi.fn();
    const remove = register('/', handler);

    press({ key: '/' });
    remove();
    press({ key: '/' });

    expect(handler).toHaveBeenCalledTimes(1);
  });
});
