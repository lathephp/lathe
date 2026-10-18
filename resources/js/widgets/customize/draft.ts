import type { LayoutDraft } from '../types';

/** The grid columns of each size the menu offers, as CardWidth sends them. */
export const sizeColumns: Record<string, number> = { '1/4': 3, '1/3': 4, '1/2': 6, '2/3': 8, full: 12 };

/** The list with the item at from moved to to. */
export function move<T>(list: T[], from: number, to: number): T[] {
  const next = [...list];
  const [item] = next.splice(from, 1);

  if (item !== undefined) {
    next.splice(to, 0, item);
  }

  return next;
}

export function resize(draft: LayoutDraft, uriKey: string, width: string): LayoutDraft {
  return {
    ...draft,
    shown: draft.shown.map((widget) => (widget.uriKey === uriKey ? { ...widget, width, columns: sizeColumns[width] ?? widget.columns } : widget)),
  };
}

export function hide(draft: LayoutDraft, uriKey: string): LayoutDraft {
  return {
    shown: draft.shown.filter((widget) => widget.uriKey !== uriKey),
    hidden: [...draft.hidden, ...draft.shown.filter((widget) => widget.uriKey === uriKey)],
  };
}

/** Shows a hidden card again, at the end of the page. */
export function addBack(draft: LayoutDraft, uriKey: string): LayoutDraft {
  return {
    shown: [...draft.shown, ...draft.hidden.filter((widget) => widget.uriKey === uriKey)],
    hidden: draft.hidden.filter((widget) => widget.uriKey !== uriKey),
  };
}

/** The PUT body's widgets: the whole ordered list, shown cards first. */
export function toPayload(draft: LayoutDraft): { uriKey: string; width: string; hidden: boolean }[] {
  return [
    ...draft.shown.map((widget) => ({ uriKey: widget.uriKey, width: widget.width, hidden: false })),
    ...draft.hidden.map((widget) => ({ uriKey: widget.uriKey, width: widget.width, hidden: true })),
  ];
}
