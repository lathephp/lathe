// @vitest-environment jsdom
import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import {
  Breadcrumb,
  BreadcrumbEllipsis,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '../breadcrumb';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '../empty';
import {
  Alert,
  AlertAction,
  AlertDescription,
  AlertTitle,
  Avatar,
  AvatarBadge,
  AvatarFallback,
  AvatarGroup,
  AvatarGroupCount,
  Card,
  CardAction,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
  Progress,
  ProgressLabel,
  ProgressValue,
  Separator,
  Skeleton,
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableFooter,
  TableHead,
  TableHeader,
  TableRow,
} from '../index';
import {
  Item,
  ItemActions,
  ItemContent,
  ItemDescription,
  ItemFooter,
  ItemGroup,
  ItemHeader,
  ItemMedia,
  ItemSeparator,
  ItemTitle,
} from '../item';
import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from '../pagination';
import { ScrollArea } from '../scroll-area';
import { ShimmerBar } from '../shimmer-bar';

afterEach(cleanup);

describe('Card and Alert', () => {
  it('render each part in its slot', () => {
    render(
      <>
        <Card size="sm">
          <CardHeader>
            <CardTitle>Revenue</CardTitle>
            <CardDescription>This month</CardDescription>
            <CardAction>Export</CardAction>
          </CardHeader>
          <CardContent>$1,200</CardContent>
          <CardFooter>Updated today</CardFooter>
        </Card>
        <Alert variant="destructive">
          <AlertTitle>Payment failed</AlertTitle>
          <AlertDescription>The card was declined.</AlertDescription>
          <AlertAction>Retry</AlertAction>
        </Alert>
      </>,
    );

    expect(screen.getByText('Revenue').getAttribute('data-slot')).toBe('card-title');
    expect(screen.getByText('$1,200').closest('[data-slot="card"]')?.getAttribute('data-size')).toBe('sm');
    expect(screen.getByRole('alert').className).toContain('cn-alert-variant-destructive');
    expect(screen.getByText('Retry').getAttribute('data-slot')).toBe('alert-action');
  });
});

describe('Table', () => {
  it('renders a table inside its scroll container', () => {
    render(
      <Table>
        <TableCaption>Orders</TableCaption>
        <TableHeader>
          <TableRow>
            <TableHead>Number</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow>
            <TableCell>1001</TableCell>
          </TableRow>
        </TableBody>
        <TableFooter>
          <TableRow>
            <TableCell>1 order</TableCell>
          </TableRow>
        </TableFooter>
      </Table>,
    );

    expect(screen.getByRole('table', { name: 'Orders' }).parentElement?.getAttribute('data-slot')).toBe(
      'table-container',
    );
    expect(screen.getByRole('columnheader').textContent).toBe('Number');
    expect(screen.getAllByRole('cell').map((cell) => cell.textContent)).toEqual(['1001', '1 order']);
  });
});

describe('Separator, Skeleton, Progress and ShimmerBar', () => {
  it('render their roles and values', () => {
    render(
      <>
        <Separator orientation="vertical" />
        <Skeleton data-testid="skeleton" />
        <Progress value={40}>
          <ProgressLabel>Upload</ProgressLabel>
          <ProgressValue />
        </Progress>
        <ShimmerBar data-testid="shimmer" />
      </>,
    );

    expect(screen.getByRole('separator', { hidden: true }).getAttribute('data-orientation')).toBe('vertical');
    expect(screen.getByTestId('skeleton').className).toContain('cn-skeleton');
    expect(screen.getByRole('progressbar').getAttribute('aria-valuenow')).toBe('40');
    expect(screen.getByText('40%').getAttribute('data-slot')).toBe('progress-value');
    expect(screen.getByTestId('shimmer').getAttribute('data-slot')).toBe('shimmer-bar');
  });
});

describe('Avatar', () => {
  it('shows the fallback while the image has not loaded', () => {
    render(
      <AvatarGroup>
        <Avatar size="lg">
          <AvatarFallback>AU</AvatarFallback>
          <AvatarBadge />
        </Avatar>
        <AvatarGroupCount>+3</AvatarGroupCount>
      </AvatarGroup>,
    );

    expect(screen.getByText('AU').getAttribute('data-slot')).toBe('avatar-fallback');
    expect(screen.getByText('AU').closest('[data-slot="avatar"]')?.getAttribute('data-size')).toBe('lg');
    expect(screen.getByText('+3').getAttribute('data-slot')).toBe('avatar-group-count');
  });
});

describe('Empty and Item', () => {
  it('render each part in its slot, and an item as the element given to render', () => {
    render(
      <>
        <Empty>
          <EmptyHeader>
            <EmptyMedia variant="icon">!</EmptyMedia>
            <EmptyTitle>No orders</EmptyTitle>
            <EmptyDescription>Create one to start.</EmptyDescription>
          </EmptyHeader>
          <EmptyContent>New order</EmptyContent>
        </Empty>
        <ItemGroup>
          {/* eslint-disable-next-line jsx-a11y/anchor-has-content -- the component passes its children to the anchor. */}
          <Item variant="outline" render={<a href="/lathe/orders/1" />}>
            <ItemHeader>Header</ItemHeader>
            <ItemMedia variant="icon">#</ItemMedia>
            <ItemContent>
              <ItemTitle>Order 1</ItemTitle>
              <ItemDescription>Paid</ItemDescription>
            </ItemContent>
            <ItemActions>Open</ItemActions>
            <ItemFooter>Footer</ItemFooter>
          </Item>
          <ItemSeparator />
        </ItemGroup>
      </>,
    );

    expect(screen.getByText('No orders').getAttribute('data-slot')).toBe('empty-title');
    expect(screen.getByText('!').getAttribute('data-variant')).toBe('icon');
    const item = screen.getByRole('link', { name: /Order 1/ });
    expect(item.getAttribute('href')).toBe('/lathe/orders/1');
    expect(item.getAttribute('data-variant')).toBe('outline');
    expect(screen.getByRole('list').getAttribute('data-slot')).toBe('item-group');
  });
});

describe('Breadcrumb and Pagination', () => {
  it('label their navigation and mark the current page', () => {
    render(
      <>
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <BreadcrumbLink href="/lathe">Home</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbEllipsis />
            </BreadcrumbItem>
            <BreadcrumbItem>
              <BreadcrumbLink render={<span />}>Orders</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbItem>
              <BreadcrumbPage>Order 1</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>
        <Pagination>
          <PaginationContent>
            <PaginationItem>
              <PaginationPrevious href="?page=1" />
            </PaginationItem>
            <PaginationItem>
              <PaginationLink href="?page=2" isActive>
                2
              </PaginationLink>
            </PaginationItem>
            <PaginationItem>
              <PaginationEllipsis />
            </PaginationItem>
            <PaginationItem>
              <PaginationNext href="?page=3" text="Forward" aria-label="Next page" />
            </PaginationItem>
          </PaginationContent>
        </Pagination>
      </>,
    );

    expect(screen.getByRole('navigation', { name: 'Breadcrumb' }).getAttribute('data-slot')).toBe('breadcrumb');
    expect(screen.getByRole('link', { name: 'Home' }).getAttribute('href')).toBe('/lathe');
    expect(screen.getByText('Orders').tagName).toBe('SPAN');
    expect(screen.getByText('Order 1').getAttribute('aria-current')).toBe('page');
    expect(screen.getByText('More').className).toBe('sr-only');
    expect(screen.getByRole('navigation', { name: 'Pagination' }).getAttribute('data-slot')).toBe('pagination');
    expect(screen.getByRole('button', { name: '2' }).getAttribute('aria-current')).toBe('page');
    expect(screen.getByRole('button', { name: 'Go to previous page' }).getAttribute('href')).toBe('?page=1');
    expect(screen.getByRole('button', { name: 'Next page' }).textContent).toBe('Forward');
    expect(screen.getByText('More pages').className).toBe('sr-only');
  });
});

describe('ScrollArea', () => {
  it('wraps its content in a scrolling viewport', () => {
    render(
      <ScrollArea className="h-20">
        <p>Long content</p>
      </ScrollArea>,
    );

    expect(screen.getByText('Long content').closest('[data-slot="scroll-area-viewport"]')).not.toBeNull();
  });
});
