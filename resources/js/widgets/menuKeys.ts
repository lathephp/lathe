import { type RefObject, useEffectEvent, useLayoutEffect } from 'react';

const items = (root: HTMLElement | null): HTMLElement[] => [...(root?.querySelectorAll<HTMLElement>('[role="menuitem"]') ?? [])];

/**
 * The behaviour of a first-paint menu, on the element around its trigger and its list. The trigger
 * is the `[aria-haspopup="menu"]` button inside it. The first item takes focus when the menu opens.
 * The arrow keys wrap, Home and End go to the ends, and a typed letter goes to the next item that
 * starts with it (letters typed within 500 ms add up). Escape closes the menu and focuses the
 * trigger, and so does a press outside the element. The keys it uses stop at the menu, so a page
 * shortcut never sees them.
 */
export function useMenuKeys(root: RefObject<HTMLElement | null>, open: boolean, close: () => void): void {
  const press = useEffectEvent((event: KeyboardEvent, typed: { text: string; at: number }) => {
    const { key } = event;
    const list = items(root.current);
    const at = list.indexOf(document.activeElement as HTMLElement);
    let to: HTMLElement | undefined;

    if (key === 'Escape') {
      close();
      root.current?.querySelector<HTMLElement>('[aria-haspopup="menu"]')?.focus();
    } else if (key === 'ArrowDown') {
      to = list[(at + 1) % list.length];
    } else if (key === 'ArrowUp') {
      to = list[at < 1 ? list.length - 1 : at - 1];
    } else if (key === 'Home') {
      to = list[0];
    } else if (key === 'End') {
      to = list[list.length - 1];
    } else if (key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey && (key !== ' ' || typed.text !== '')) {
      typed.text = event.timeStamp - typed.at > 500 ? key : typed.text + key;
      typed.at = event.timeStamp;
      // One letter looks past the focused item, so repeating it steps through the matches.
      const from = typed.text.length === 1 ? at + 1 : Math.max(at, 0);
      to = [...list.slice(from), ...list.slice(0, from)].find((item) => item.textContent.trim().toLowerCase().startsWith(typed.text.toLowerCase()));
    } else {
      return;
    }

    event.preventDefault();
    event.stopPropagation();
    to?.focus();
  });
  const outside = useEffectEvent((event: PointerEvent) => {
    if (!(event.target instanceof Node) || root.current?.contains(event.target) !== true) {
      close();
    }
  });

  // A layout effect, so a menu that would run past the window's bottom opens above its button
  // before it is drawn below it.
  useLayoutEffect(() => {
    const element = root.current;

    if (!open || element === null) {
      return;
    }

    const menu = element.querySelector<HTMLElement>('[role=menu]');

    if (menu !== null && menu.getBoundingClientRect().bottom > window.innerHeight) {
      Object.assign(menu.style, { top: 'auto', bottom: 'calc(100% + 4px)' });
      menu.dataset.side = 'top';
    }

    const typed = { text: '', at: 0 };
    const onKeyDown = (event: KeyboardEvent) => {
      press(event, typed);
    };
    items(element)[0]?.focus();
    element.addEventListener('keydown', onKeyDown);
    document.addEventListener('pointerdown', outside);

    return () => {
      element.removeEventListener('keydown', onKeyDown);
      document.removeEventListener('pointerdown', outside);
    };
  }, [open, root]);
}
