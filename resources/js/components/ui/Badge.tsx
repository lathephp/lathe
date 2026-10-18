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

export function isBadgeTone(value: unknown): value is BadgeTone {
  return typeof value === 'string' && value in tones;
}

/** A value as a small pill in one of the theme's tones. */
export function Badge({ tone, children, className }: { tone: BadgeTone; children: ReactNode; className?: string }) {
  return (
    <span data-lathe-badge-tone={tone} className={cn('inline-flex h-5 items-center gap-1 rounded-sm px-2 text-xs font-medium whitespace-nowrap', tones[tone], className)}>
      {children}
    </span>
  );
}
