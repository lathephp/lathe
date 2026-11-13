import { __panel } from '@lathephp/core';
import { cleanup, configure } from '@testing-library/react';
import { afterAll, afterEach, beforeAll, vi } from 'vitest';
import { registerBuiltinFields } from '../fields/builtin';
import { preloadChoiceForms } from '../fields/choice/lazy';
import { preloadDateFields } from '../fields/date/lazy';
import { preloadRelationForms } from '../fields/relation/lazy';
import { preloadRepeaterFields } from '../fields/repeater/lazy';
import { forgetSaves } from '../index/inlineSave';
import { IndexFooter, preloadTabDialogs, TableSummaryRow } from '../index/lazy';
import { TokenInput } from '../fields/number/lazy';
import { SlugInput } from '../fields/slug/lazy';
import { LazyFileControl, Uploader } from '../fields/upload/file';

registerBuiltinFields();

// A lazy chunk on a loaded CI runner can take over the default second to arrive; a find or waitFor
// still fails on what it asserts, only later.
configure({ asyncUtilTimeout: 3000 });

// The relation pickers, the repeater, the date picker, the file control, the index footer and the saved-view dialogs are lazy chunks; loaded up front, a test renders them at once.
beforeAll(async () => {
  await Promise.all([preloadRelationForms(), preloadChoiceForms(), preloadRepeaterFields(), preloadTabDialogs(), TableSummaryRow.preload(), IndexFooter.preload(), TokenInput.preload(), SlugInput.preload(), Uploader.preload(), LazyFileControl.preload(), preloadDateFields()]);
});

// jsdom has no layout for ranges, and ProseMirror measures one when a Tiptap update
// scrolls the selection into view, sometimes after its test has finished.
if (typeof Range !== 'undefined' && typeof Range.prototype.getClientRects !== 'function') {
  Range.prototype.getClientRects = () => [] as unknown as DOMRectList;
  Range.prototype.getBoundingClientRect = () => new DOMRect(0, 0, 0, 0);
}

// Vitest globals are off, so Testing Library cannot register its own cleanup.
afterEach(async () => {
  cleanup();
  // A save a test left unanswered would hold its record's next saves in the tests after it.
  forgetSaves();
  __panel.toasts.set([]);
  __panel.sharedProps.set({});
  __panel.setTranslator(null);
  // An unmounted <Head> updates the head on a 1 ms timer; let it run while jsdom is still
  // here. Under fake timers it never fires on its own, so there is nothing to wait for.
  if (!vi.isFakeTimers()) {
    await new Promise((resolve) => setTimeout(resolve, 2));
  }
});

// Inertia saves the scroll position from a capturing window listener debounced by 100 ms, so a
// scroll in the file's last test can fire it after jsdom is gone ("window is not defined").
const inertiaScrollDebounceMs = 100;
let lastScrollAt: number | null = null;

if (typeof window !== 'undefined') {
  window.addEventListener(
    'scroll',
    () => {
      lastScrollAt = Date.now();
    },
    true,
  );
}

afterAll(async () => {
  if (lastScrollAt !== null && !vi.isFakeTimers()) {
    const wait = lastScrollAt + inertiaScrollDebounceMs + 10 - Date.now();

    if (wait > 0) {
      await new Promise((resolve) => setTimeout(resolve, wait));
    }
  }
});

// jsdom does not implement scrollIntoView; the form's error summary calls it after a failed save.
if (typeof Element !== 'undefined' && typeof Element.prototype.scrollIntoView !== 'function') {
  Element.prototype.scrollIntoView = () => undefined;
}
