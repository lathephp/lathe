// @vitest-environment jsdom
import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { toast } from 'sonner';
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  Command,
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
  CommandShortcut,
} from '../command';
import { HoverCard, HoverCardContent, HoverCardTrigger } from '../hover-card';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogMedia,
  AlertDialogTitle,
  AlertDialogTrigger,
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuSeparator,
  DropdownMenuShortcut,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
  DropdownMenuTrigger,
  Popover,
  PopoverContent,
  PopoverDescription,
  PopoverHeader,
  PopoverTitle,
  PopoverTrigger,
  Sheet,
  SheetClose,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '../index';
import { Toaster } from '../sonner';

// cmdk measures its list and scrolls the active item into view; jsdom does neither.
globalThis.ResizeObserver = class {
  observe() {}
  unobserve() {}
  disconnect() {}
};
Element.prototype.scrollIntoView = () => undefined;

afterEach(cleanup);

describe('Dialog, AlertDialog and Sheet', () => {
  it('open a dialog from its trigger and close it with the close button', async () => {
    render(
      <Dialog>
        <DialogTrigger>Edit order</DialogTrigger>
        <DialogContent closeLabel="Dismiss">
          <DialogHeader>
            <DialogTitle>Edit order</DialogTitle>
            <DialogDescription>Change the address.</DialogDescription>
          </DialogHeader>
          <DialogFooter showCloseButton>
            <DialogClose>Done</DialogClose>
          </DialogFooter>
        </DialogContent>
      </Dialog>,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Edit order' }));
    const dialog = await screen.findByRole('dialog', { name: 'Edit order' });

    expect(dialog.getAttribute('aria-describedby')).toBe(screen.getByText('Change the address.').id);
    expect(screen.getByRole('button', { name: 'Close' }).className).toContain('cn-button-variant-outline');

    fireEvent.click(screen.getByRole('button', { name: 'Dismiss' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).toBeNull();
    });
  });

  it('close an alert dialog with Escape', async () => {
    render(
      <AlertDialog>
        <AlertDialogTrigger>Delete</AlertDialogTrigger>
        <AlertDialogContent size="sm">
          <AlertDialogHeader>
            <AlertDialogMedia>!</AlertDialogMedia>
            <AlertDialogTitle>Delete the order?</AlertDialogTitle>
            <AlertDialogDescription>It cannot be restored.</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Keep</AlertDialogCancel>
            <AlertDialogAction variant="destructive">Delete it</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Delete' }));
    const dialog = await screen.findByRole('alertdialog', { name: 'Delete the order?' });

    expect(dialog.getAttribute('data-size')).toBe('sm');
    expect(screen.getByRole('button', { name: 'Delete it' }).className).toContain('cn-button-variant-destructive');

    fireEvent.keyDown(dialog, { key: 'Escape' });

    await waitFor(() => {
      expect(screen.queryByRole('alertdialog')).toBeNull();
    });
  });

  it('open a sheet on the side asked', async () => {
    render(
      <Sheet>
        <SheetTrigger>Filters</SheetTrigger>
        <SheetContent side="left">
          <SheetHeader>
            <SheetTitle>Filters</SheetTitle>
            <SheetDescription>Narrow the list.</SheetDescription>
          </SheetHeader>
          <SheetFooter>
            <SheetClose>Apply</SheetClose>
          </SheetFooter>
        </SheetContent>
      </Sheet>,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Filters' }));
    const sheet = await screen.findByRole('dialog', { name: 'Filters' });

    expect(sheet.getAttribute('data-side')).toBe('left');
    expect(screen.getByText('Close').className).toBe('sr-only');
  });
});

describe('Popover, Tooltip and HoverCard', () => {
  it('open a popover from its trigger', async () => {
    render(
      <Popover>
        <PopoverTrigger>Share</PopoverTrigger>
        <PopoverContent>
          <PopoverHeader>
            <PopoverTitle>Share the report</PopoverTitle>
            <PopoverDescription>Anyone with the link can view.</PopoverDescription>
          </PopoverHeader>
        </PopoverContent>
      </Popover>,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Share' }));

    expect((await screen.findByRole('dialog', { name: 'Share the report' })).getAttribute('data-slot')).toBe(
      'popover-content',
    );
  });

  it('show a tooltip and a hover card while open', async () => {
    render(
      <TooltipProvider>
        <Tooltip open>
          <TooltipTrigger>Help</TooltipTrigger>
          <TooltipContent>Opens the docs</TooltipContent>
        </Tooltip>
        <HoverCard open>
          <HoverCardTrigger href="/lathe/users/1">Ada</HoverCardTrigger>
          <HoverCardContent>Admin since 2020</HoverCardContent>
        </HoverCard>
      </TooltipProvider>,
    );

    expect((await screen.findByText('Opens the docs')).closest('[data-slot="tooltip-content"]')).not.toBeNull();
    expect(screen.getByText('Admin since 2020').closest('[data-slot="hover-card-content"]')).not.toBeNull();
  });
});

describe('DropdownMenu', () => {
  it('opens its menu and moves through the items with the arrow keys', async () => {
    const select = vi.fn();
    render(
      <DropdownMenu>
        <DropdownMenuTrigger>Actions</DropdownMenuTrigger>
        <DropdownMenuContent>
          <DropdownMenuGroup>
            <DropdownMenuLabel>Order</DropdownMenuLabel>
            <DropdownMenuItem onClick={select}>
              Refund
              <DropdownMenuShortcut>R</DropdownMenuShortcut>
            </DropdownMenuItem>
            <DropdownMenuItem variant="destructive">Delete</DropdownMenuItem>
          </DropdownMenuGroup>
          <DropdownMenuSeparator />
          <DropdownMenuCheckboxItem checked>Show archived</DropdownMenuCheckboxItem>
          <DropdownMenuRadioGroup value="asc">
            <DropdownMenuRadioItem value="asc">Oldest first</DropdownMenuRadioItem>
          </DropdownMenuRadioGroup>
          <DropdownMenuSub>
            <DropdownMenuSubTrigger>Export</DropdownMenuSubTrigger>
            <DropdownMenuSubContent>
              <DropdownMenuItem>CSV</DropdownMenuItem>
            </DropdownMenuSubContent>
          </DropdownMenuSub>
        </DropdownMenuContent>
      </DropdownMenu>,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Actions' }));
    const menu = await screen.findByRole('menu');
    const refund = screen.getByRole('menuitem', { name: /^Refund/ });

    expect(screen.getByRole('menuitem', { name: 'Delete' }).getAttribute('data-variant')).toBe('destructive');
    expect(screen.getByRole('menuitemcheckbox', { name: 'Show archived' }).getAttribute('aria-checked')).toBe('true');
    expect(screen.getByRole('menuitemradio', { name: 'Oldest first' }).getAttribute('aria-checked')).toBe('true');

    refund.focus();
    fireEvent.keyDown(menu, { key: 'ArrowDown' });

    await waitFor(() => {
      expect(document.activeElement?.textContent).toBe('Delete');
    });

    fireEvent.click(refund);

    expect(select).toHaveBeenCalledTimes(1);
  });
});

describe('Command', () => {
  it('filters its items by the text typed', async () => {
    render(
      <Command>
        <CommandInput placeholder="Search" />
        <CommandList>
          <CommandEmpty>No results</CommandEmpty>
          <CommandGroup heading="Pages">
            <CommandItem>
              Orders
              <CommandShortcut>G O</CommandShortcut>
            </CommandItem>
            <CommandSeparator />
            <CommandItem>Users</CommandItem>
          </CommandGroup>
        </CommandList>
      </Command>,
    );

    fireEvent.change(screen.getByPlaceholderText('Search'), { target: { value: 'use' } });

    await waitFor(() => {
      expect(screen.getAllByRole('option').map((option) => option.textContent)).toEqual(['Users']);
    });
  });

  it('opens as a dialog with a hidden title', async () => {
    render(
      <CommandDialog open title="Find anything">
        <Command>
          <CommandInput placeholder="Search" />
          <CommandList>
            <CommandItem>Orders</CommandItem>
          </CommandList>
        </Command>
      </CommandDialog>,
    );

    expect((await screen.findByText('Find anything')).closest('.sr-only')).not.toBeNull();
    expect(screen.getByRole('option', { name: 'Orders' }).getAttribute('data-slot')).toBe('command-item');
  });
});

describe('Toaster', () => {
  it('shows a toast that sonner sends', async () => {
    render(<Toaster theme="dark" />);

    act(() => {
      toast('Order saved');
    });

    expect((await screen.findByText('Order saved')).closest('[data-sonner-toast]')).not.toBeNull();
  });
});
