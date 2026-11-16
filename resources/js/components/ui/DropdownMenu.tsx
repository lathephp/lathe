'use no memo';

import type { ComponentProps } from 'react';
import { menuClasses } from '../../lib/menuKeys';

// The kit DropdownMenu's trigger and item, copied for the first-paint menus (the user menu, a
// saved view's options, a dashboard card's ⋯ menu), which useMenuKeys moves. The trigger is a
// plain button the menu opens from; an item draws with the kit item's classes.
export function DropdownMenuTrigger({ open, ...props }: Omit<ComponentProps<'button'>, 'type'> & { open: boolean }) {
  return <button type="button" aria-haspopup="menu" aria-expanded={open} data-slot="dropdown-menu-trigger" data-popup-open={open ? '' : undefined} {...props} />;
}

export function DropdownMenuItem({ className, ...props }: Omit<ComponentProps<'button'>, 'type' | 'role'>) {
  return <button type="button" role="menuitem" data-slot="dropdown-menu-item" data-variant="default" className={`${menuClasses.item} ${className ?? ''}`} {...props} />;
}
