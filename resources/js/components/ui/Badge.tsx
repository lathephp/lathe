import type { ReactNode } from 'react';
import { cn } from '../../lib/cn';

export type BadgeVariant = 'default' | 'secondary' | 'outline' | 'destructive' | 'success' | 'warning' | 'info';

export type BadgeTone = 'neutral' | 'primary' | 'info' | 'success' | 'warning' | 'danger';

// The tones and types the server names, as the variant that draws each; a menu badge sends destructive, a view danger.
export const toneVariants: Record<string, BadgeVariant> = {
  neutral: 'secondary',
  primary: 'default',
  danger: 'destructive',
  destructive: 'destructive',
  info: 'info',
  success: 'success',
  warning: 'warning',
};

// A status dot takes the variant's solid colour; an ended status, such as cancelled, the chart's grey.
const dots: Record<BadgeVariant, string> = {
  default: 'bg-primary',
  secondary: 'bg-chart-other',
  outline: 'bg-chart-other',
  destructive: 'bg-destructive',
  success: 'bg-success',
  warning: 'bg-warning',
  info: 'bg-info',
};

export function isBadgeTone(value: unknown): value is BadgeTone {
  return typeof value === 'string' && value !== 'destructive' && value in toneVariants;
}

/** A value as a small pill in the active style's badge look, with a dot when it is a status. */
export function Badge({ variant = 'default', dot = false, children, className }: { variant?: BadgeVariant; dot?: boolean; children: ReactNode; className?: string }) {
  return (
    <span data-slot="badge" className={cn('lathe-badge cn-badge', `cn-badge-variant-${variant}`, className)}>
      {dot && <span data-lathe-badge-dot="" aria-hidden="true" className={cn('size-1.5 shrink-0 rounded-full', dots[variant])} />}
      {children}
    </span>
  );
}
