import type { ReactNode } from 'react';
import { cn } from '../../lib/cn';

export type BadgeTone = 'neutral' | 'primary' | 'info' | 'success' | 'warning' | 'danger';

// Each tone is a -subtle fill with its own text, which reads at 4.5:1 in both themes.
const tones: Record<BadgeTone, string> = {
  neutral: 'bg-secondary text-secondary-foreground',
  primary: 'bg-primary-subtle text-primary-subtle-foreground',
  info: 'bg-info-subtle text-info-subtle-foreground',
  success: 'bg-success-subtle text-success-subtle-foreground',
  warning: 'bg-warning-subtle text-warning-subtle-foreground',
  danger: 'bg-destructive-subtle text-destructive-subtle-foreground',
};

// A status dot takes the tone's solid colour; an ended status, such as cancelled, the chart's grey.
const dots: Record<BadgeTone, string> = {
  neutral: 'bg-chart-other',
  primary: 'bg-primary',
  info: 'bg-info',
  success: 'bg-success',
  warning: 'bg-warning',
  danger: 'bg-destructive',
};

export function isBadgeTone(value: unknown): value is BadgeTone {
  return typeof value === 'string' && value in tones;
}

/** A value as a small pill in one of the theme's tones, with a dot when it is a status. */
export function Badge({ tone, dot = false, children, className }: { tone: BadgeTone; dot?: boolean; children: ReactNode; className?: string }) {
  return (
    <span data-lathe-badge-tone={tone} className={cn('inline-flex h-[22px] items-center gap-1.5 rounded-[6px] px-2 text-xs leading-4 font-medium whitespace-nowrap', tones[tone], className)}>
      {dot && <span data-lathe-badge-dot="" aria-hidden="true" className={cn('size-1.5 shrink-0 rounded-full', dots[tone])} />}
      {children}
    </span>
  );
}
