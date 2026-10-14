import { describe, expect, it } from 'vitest';

// ProseMirror measures a Range when an editor scrolls its selection into view, which a
// Tiptap update can do after its test has finished; jsdom has no layout for ranges.
describe('the test environment', () => {
  it('gives a Range empty client rects and a zero bounding rect', () => {
    const range = document.createRange();

    expect(range.getClientRects()).toHaveLength(0);
    expect(range.getBoundingClientRect().width).toBe(0);
  });
});
