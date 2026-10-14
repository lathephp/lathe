import { __panel } from '@lathephp/core';
import { cleanup } from '@testing-library/react';
import { afterEach, beforeAll } from 'vitest';
import { registerBuiltinFields } from '../fields/builtin';
import { preloadRelationForms } from '../fields/relation/lazy';

registerBuiltinFields();

// The relation pickers are lazy chunks; loaded up front, a test renders them at once.
beforeAll(async () => {
  await preloadRelationForms();
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
