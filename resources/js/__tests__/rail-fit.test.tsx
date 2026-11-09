import { act } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { railFolds } from '../index/useRailFit';
import { renderPage } from './support';

describe('railFolds', () => {
  it('folds the rail when the columns need more than the body leaves beside it', () => {
    expect(railFolds(1024, 907)).toBe(true);
  });

  it('keeps the rail open when the columns fit beside it', () => {
    expect(railFolds(1024, 700)).toBe(false);
    expect(railFolds(1600, 907)).toBe(false);
  });
});

// jsdom has no layout: each width below is what a browser would measure.
let bodyWidth = 0;
let needed = 0;
const observers: { callback: ResizeObserverCallback; targets: Element[]; disconnected: boolean }[] = [];

function folded(): boolean {
  return document.querySelector('.lathe-index-body')?.hasAttribute('data-rail-folded') === true;
}

/** Every live observer runs its callback, as the browser does after a resize. */
function fire() {
  act(() => {
    observers
      .filter((observer) => !observer.disconnected)
      .forEach((observer) => {
        observer.callback(
          observer.targets.map((target) => ({ target }) as ResizeObserverEntry),
          {} as ResizeObserver,
        );
      });
  });
}

beforeEach(() => {
  observers.length = 0;
  vi.stubGlobal(
    'ResizeObserver',
    class {
      private readonly entry: (typeof observers)[number];

      constructor(callback: ResizeObserverCallback) {
        this.entry = { callback, targets: [], disconnected: false };
        observers.push(this.entry);
      }

      observe(target: Element) {
        this.entry.targets.push(target);
      }

      unobserve(target: Element) {
        this.entry.targets = this.entry.targets.filter((other) => other !== target);
      }

      disconnect() {
        this.entry.disconnected = true;
      }
    },
  );
  vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockImplementation(function (this: HTMLElement) {
    if (this.classList.contains('lathe-index-body')) {
      return new DOMRect(0, 0, bodyWidth, 600);
    }

    if (this.hasAttribute('data-lathe-table')) {
      // The table is w-full: at its own width it fills the card, which the open rail narrows by 236 px.
      const card = bodyWidth - (folded() ? 0 : 236);
      const width = this.style.width === 'max-content' ? needed : Math.max(needed, card);

      return new DOMRect(0, 0, width, 400);
    }

    return new DOMRect(0, 0, 0, 0);
  });
});

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

function renderOrders() {
  return renderPage(
    'Resources/Index',
    {
      resource: {
        uriKey: 'orders',
        label: 'Orders',
        singularLabel: 'Order',
        perPageOptions: [25, 50, 100],
        searchable: false,
        abilities: { create: false },
        definition: { debounce: 0.5, savedTabs: true },
      },
      table: { columns: [], sortKeys: [], rows: [{ id: 1, title: 'ORD-1', cells: {}, abilities: { view: true } }], rowKey: 'id' },
      state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
      count: null,
      tabs: {
        items: [{ key: 'all', name: 'All', icon: null, badge: null, badgeType: null, deferredBadge: false }],
        active: 'all',
        saved: [],
        activeSaved: null,
      },
      lenses: [],
    },
    { url: '/lathe/resources/orders' },
  );
}

describe('the views rail beside a wide table', () => {
  it('folds into the Views menu when the columns would not fit beside it', () => {
    bodyWidth = 1024;
    needed = 907;
    renderOrders();

    expect(folded()).toBe(true);
  });

  it('stays open when the columns fit beside it', () => {
    bodyWidth = 1600;
    needed = 907;
    renderOrders();

    expect(folded()).toBe(false);
  });

  it('flips once when a column is hidden, and folding does not flip it back', () => {
    bodyWidth = 1024;
    needed = 907;
    renderOrders();

    needed = 700;
    fire();
    expect(folded()).toBe(false);
    fire();
    expect(folded()).toBe(false);

    needed = 907;
    fire();
    expect(folded()).toBe(true);
    fire();
    expect(folded()).toBe(true);
  });

  it('disconnects its observer on unmount', () => {
    bodyWidth = 1024;
    needed = 907;
    const { unmount } = renderOrders();
    const body = document.querySelector('.lathe-index-body');
    const mine = observers.filter((observer) => observer.targets.includes(body as Element));

    expect(mine).toHaveLength(1);
    unmount();
    expect(mine.every((observer) => observer.disconnected)).toBe(true);
  });
});
