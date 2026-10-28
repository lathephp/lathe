import { __panel } from '@lathephp/core';
import { cleanup, configure } from '@testing-library/react';
import { afterEach, beforeAll, vi } from 'vitest';
import { registerBuiltinFields } from '../fields/builtin';
import { preloadChoiceForms } from '../fields/choice/lazy';
import { preloadDateFields } from '../fields/date/lazy';
import { preloadRelationForms } from '../fields/relation/lazy';
import { preloadRepeaterFields } from '../fields/repeater/lazy';
import { preloadTabDialogs, TableSummaryRow } from '../index/lazy';
import { TokenInput } from '../fields/number/lazy';
import { SlugInput } from '../fields/slug/lazy';
import { Uploader } from '../fields/upload/file';

registerBuiltinFields();

// A lazy chunk on a loaded CI runner can take over the default second to arrive; a find or waitFor
// still fails on what it asserts, only later.
configure({ asyncUtilTimeout: 3000 });

// The relation pickers, the repeater, the date picker and the saved-view dialogs are lazy chunks; loaded up front, a test renders them at once.
beforeAll(async () => {
  await Promise.all([preloadRelationForms(), preloadChoiceForms(), preloadRepeaterFields(), preloadTabDialogs(), TableSummaryRow.preload(), TokenInput.preload(), SlugInput.preload(), Uploader.preload(), preloadDateFields()]);
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
  __panel.toasts.set([]);
  __panel.sharedProps.set({});
  __panel.setTranslator(null);
  // An unmounted <Head> updates the head on a 1 ms timer; let it run while jsdom is still
  // here. Under fake timers it never fires on its own, so there is nothing to wait for.
  if (!vi.isFakeTimers()) {
    await new Promise((resolve) => setTimeout(resolve, 2));
  }
});

// jsdom does not implement scrollIntoView; the form's error summary calls it after a failed save.
if (typeof Element !== 'undefined' && typeof Element.prototype.scrollIntoView !== 'function') {
  Element.prototype.scrollIntoView = () => undefined;
}
