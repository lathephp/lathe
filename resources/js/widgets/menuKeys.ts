import { type RefObject, useEffect, useEffectEvent } from 'react';

const items = (root: HTMLElement | null): HTMLElement[] => [...(root?.querySelectorAll<HTMLElement>('[role^="menuitem"]') ?? [])];

/**
 * The keyboard of a card's ⋯ menu, on the element around its button and its list: the first
 * item takes focus when the menu opens, the arrow keys move between items, and Escape closes
 * it and gives the focus back to the button.
 */
export function useMenuKeys(root: RefObject<HTMLElement | null>, open: boolean, close: () => void): void {
  const press = useEffectEvent((event: KeyboardEvent) => {
    if (event.key === 'Escape') {
      event.preventDefault();
      close();
      root.current?.querySelector<HTMLElement>('[aria-haspopup="menu"]')?.focus();

      return;
    }

    const step = event.key === 'ArrowDown' ? 1 : event.key === 'ArrowUp' ? -1 : 0;

    if (step !== 0) {
      event.preventDefault();
      const list = items(root.current);
      const at = list.indexOf(document.activeElement as HTMLElement);
      list[(at + step + list.length) % list.length]?.focus();
    }
  });

  useEffect(() => {
    const element = root.current;

    if (!open || element === null) {
      return;
    }

    items(element)[0]?.focus();
    element.addEventListener('keydown', press);

    return () => {
      element.removeEventListener('keydown', press);
    };
  }, [open, root]);
}
