// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { DirectionProvider, useDirection } from '../direction';
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupAction,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarInput,
  SidebarInset,
  SidebarMenu,
  SidebarMenuAction,
  SidebarMenuBadge,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSkeleton,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
  SidebarProvider,
  SidebarRail,
  SidebarSeparator,
  SidebarTrigger,
} from '../sidebar';

// jsdom has no matchMedia; a wide screen keeps the desktop sidebar.
beforeEach(() => {
  window.matchMedia = (query: string) =>
    ({ matches: false, media: query, addEventListener() {}, removeEventListener() {} }) as unknown as MediaQueryList;
});

afterEach(cleanup);

function Panel() {
  return (
    <SidebarProvider>
      <Sidebar collapsible="icon">
        <SidebarHeader>
          <SidebarInput aria-label="Search the menu" />
        </SidebarHeader>
        <SidebarContent>
          <SidebarGroup>
            <SidebarGroupLabel>Shop</SidebarGroupLabel>
            <SidebarGroupAction aria-label="Add">+</SidebarGroupAction>
            <SidebarGroupContent>
              <SidebarMenu>
                <SidebarMenuItem>
                  {/* eslint-disable-next-line jsx-a11y/anchor-has-content -- the component passes its children to the anchor. */}
                  <SidebarMenuButton isActive tooltip="Orders" render={<a href="/lathe/resources/orders" />}>
                    Orders
                  </SidebarMenuButton>
                  <SidebarMenuAction aria-label="More">...</SidebarMenuAction>
                  <SidebarMenuBadge>12</SidebarMenuBadge>
                  <SidebarMenuSub>
                    <SidebarMenuSubItem>
                      <SidebarMenuSubButton href="/lathe/resources/orders?status=paid">Paid</SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                  </SidebarMenuSub>
                </SidebarMenuItem>
                <SidebarMenuItem>
                  <SidebarMenuSkeleton showIcon />
                </SidebarMenuItem>
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
          <SidebarSeparator />
        </SidebarContent>
        <SidebarFooter>Signed in</SidebarFooter>
        <SidebarRail />
      </Sidebar>
      <SidebarInset>
        <SidebarTrigger />
      </SidebarInset>
    </SidebarProvider>
  );
}

describe('Sidebar', () => {
  it('collapses when the trigger is pressed and expands on Ctrl+B', () => {
    const { container } = render(<Panel />);
    const sidebar = () => container.querySelector('[data-slot="sidebar"]');

    expect(sidebar()?.getAttribute('data-state')).toBe('expanded');
    expect(screen.getByRole('link', { name: 'Orders' }).hasAttribute('data-active')).toBe(true);
    expect(screen.getByRole('link', { name: 'Paid' }).getAttribute('href')).toBe('/lathe/resources/orders?status=paid');

    fireEvent.click(screen.getAllByRole('button', { name: 'Toggle Sidebar' })[1] as HTMLElement);

    expect(sidebar()?.getAttribute('data-state')).toBe('collapsed');
    expect(sidebar()?.getAttribute('data-collapsible')).toBe('icon');

    fireEvent.keyDown(window, { key: 'b', ctrlKey: true });

    expect(sidebar()?.getAttribute('data-state')).toBe('expanded');
    expect(document.cookie).toContain('sidebar_state=true');
  });
});

describe('DirectionProvider', () => {
  it('passes the reading direction down', () => {
    function Probe() {
      return <p>{useDirection()}</p>;
    }
    render(
      <DirectionProvider direction="rtl">
        <Probe />
      </DirectionProvider>,
    );

    expect(screen.getByText('rtl').tagName).toBe('P');
  });
});
