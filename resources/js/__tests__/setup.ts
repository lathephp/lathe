import { __panel } from '@lathephp/core';
import { cleanup } from '@testing-library/react';
import { afterEach, beforeAll } from 'vitest';
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
afterEach(() => {
  cleanup();
  __panel.toasts.set([]);
  __panel.sharedProps.set({});
  __panel.setTranslator(null);
});
