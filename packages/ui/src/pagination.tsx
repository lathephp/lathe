import * as React from 'react';
import { cn } from './lib/utils';

import { Button } from './button';
import { ChevronLeftIcon, ChevronRightIcon, MoreHorizontalIcon } from './lib/icons';

function Pagination({ className, 'aria-label': label = 'Pagination', ...props }: React.ComponentProps<'nav'>) {
  return (
    <nav
      aria-label={label}
      data-slot="pagination"
      className={cn('cn-pagination mx-auto flex w-full justify-center', className)}
      {...props}
    />
  );
}

function PaginationContent({ className, ...props }: React.ComponentProps<'ul'>) {
  return (
    <ul
      data-slot="pagination-content"
      className={cn('cn-pagination-content flex items-center', className)}
      {...props}
    />
  );
}

function PaginationItem({ ...props }: React.ComponentProps<'li'>) {
  return <li data-slot="pagination-item" {...props} />;
}

type PaginationLinkProps = {
  isActive?: boolean;
} & Pick<React.ComponentProps<typeof Button>, 'size'> &
  React.ComponentProps<'a'>;

function PaginationLink({ className, isActive, size = 'icon', ...props }: PaginationLinkProps) {
  return (
    <Button
      variant={isActive ? 'outline' : 'ghost'}
      size={size}
      className={cn('cn-pagination-link', className)}
      nativeButton={false}
      render={
        // eslint-disable-next-line jsx-a11y/anchor-has-content -- the children in props fill the anchor.
        <a aria-current={isActive ? 'page' : undefined} data-slot="pagination-link" data-active={isActive} {...props} />
      }
    />
  );
}

function PaginationPrevious({
  className,
  text = 'Previous',
  'aria-label': label = 'Go to previous page',
  ...props
}: React.ComponentProps<typeof PaginationLink> & { text?: string }) {
  return (
    <PaginationLink aria-label={label} size="default" className={cn('cn-pagination-previous', className)} {...props}>
      <ChevronLeftIcon data-icon="inline-start" className="cn-rtl-flip" />
      <span className="cn-pagination-previous-text hidden sm:block">{text}</span>
    </PaginationLink>
  );
}

function PaginationNext({
  className,
  text = 'Next',
  'aria-label': label = 'Go to next page',
  ...props
}: React.ComponentProps<typeof PaginationLink> & { text?: string }) {
  return (
    <PaginationLink aria-label={label} size="default" className={cn('cn-pagination-next', className)} {...props}>
      <span className="cn-pagination-next-text hidden sm:block">{text}</span>
      <ChevronRightIcon data-icon="inline-end" className="cn-rtl-flip" />
    </PaginationLink>
  );
}

function PaginationEllipsis({
  className,
  label = 'More pages',
  ...props
}: React.ComponentProps<'span'> & { label?: string }) {
  return (
    <span
      aria-hidden
      data-slot="pagination-ellipsis"
      className={cn('cn-pagination-ellipsis flex items-center justify-center', className)}
      {...props}
    >
      <MoreHorizontalIcon />
      <span className="sr-only">{label}</span>
    </span>
  );
}

export {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
};
