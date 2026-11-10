import { describe, expect, it } from 'vitest';
import { listPlace } from '../index/FilterBarEditor';

// A 440 px bar at x 200, 30 px tall at y 100.
const bar = new DOMRect(200, 100, 440, 30);

describe('the filter list place', () => {
  it('opens 320 px wide under the text cursor of an empty bar', () => {
    const place = listPlace(bar, new DOMRect(226, 104, 400, 22), false);

    expect([place.x, place.y, place.width, place.height]).toEqual([226, 100, 320, 30]);
  });

  it('follows the cursor to the right of a chip', () => {
    expect(listPlace(bar, new DOMRect(300, 104, 310, 22), false).x).toBe(300);
  });

  it('stays inside the bar when the cursor is near its end', () => {
    const place = listPlace(bar, new DOMRect(560, 104, 64, 22), false);

    expect(place.right).toBe(bar.right);
    expect(place.width).toBe(320);
  });

  it('opens under the cursor in a right-to-left bar, its right edge at the cursor', () => {
    expect(listPlace(bar, new DOMRect(214, 104, 400, 22), true).right).toBe(614);
  });

  it('stays inside a right-to-left bar when the cursor is near its end', () => {
    const place = listPlace(bar, new DOMRect(214, 104, 64, 22), true);

    expect(place.x).toBe(bar.x);
    expect(place.right).toBe(520);
  });

  it('takes the bar width when the bar is narrower than the list', () => {
    const narrow = new DOMRect(10, 0, 280, 30);
    const place = listPlace(narrow, new DOMRect(40, 4, 200, 22), false);

    expect([place.x, place.width]).toEqual([10, 280]);
  });
});
