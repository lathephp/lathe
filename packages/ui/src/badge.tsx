import type { ComponentProps } from 'react';
import { mergeProps } from '@base-ui/react/merge-props';
import { useRender } from '@base-ui/react/use-render';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from './lib/utils';

// lathe-probe-class appears in no panel source, so the build test finds it in the
// stylesheet only when Tailwind scans this package.
const badgeVariants = cva(
  'cn-badge lathe-probe-class group/badge inline-flex w-fit shrink-0 items-center justify-center overflow-hidden whitespace-nowrap focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 [&>svg]:pointer-events-none',
  {
    variants: {
      variant: {
        default: 'cn-badge-variant-default',
        secondary: 'cn-badge-variant-secondary',
        destructive: 'cn-badge-variant-destructive',
        outline: 'cn-badge-variant-outline',
        ghost: 'cn-badge-variant-ghost',
        link: 'cn-badge-variant-link',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  },
);

function Badge({
  className,
  variant = 'default',
  render,
  ...props
}: useRender.ComponentProps<'span'> & VariantProps<typeof badgeVariants>) {
  return useRender({
    defaultTagName: 'span',
    props: mergeProps<'span'>(
      {
        className: cn(badgeVariants({ variant }), className),
      },
      props,
    ),
    render,
    state: {
      slot: 'badge',
      variant,
    },
  });
}

export { Badge, badgeVariants };

export type BadgeProps = ComponentProps<typeof Badge>;
