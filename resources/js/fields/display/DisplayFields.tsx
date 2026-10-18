import { createElement } from 'react';
import { Badge, type BadgeTone } from '../../components/ui/Badge';
import { cn } from '../../lib/cn';
import type { DetailFieldProps, IndexFieldProps } from '../registry';
import type { FieldSchema } from '../types';
import { iconNamed } from './icons';
import { seriesOf, Sparkline } from './Sparkline';

/** What the server added to a cell beyond its value and display: a badge type, an icon, a color, a line style. */
function extra(source: object, key: string): unknown {
  return Object.entries(source).find(([name]) => name === key)?.[1];
}

function text(value: unknown): string {
  return typeof value === 'string' ? value : '';
}

const badgeTones: Record<string, BadgeTone> = { info: 'info', success: 'success', warning: 'warning', danger: 'danger', failed: 'danger', loading: 'neutral' };

// A status draws its own icon; a badge draws one only when the server sent it.
const statusIcons: Record<string, string> = { loading: 'loader-circle', failed: 'circle-x', success: 'circle-check' };

function BadgeView({ label, type, icon, status }: { label: string; type: string; icon: string; status: boolean }) {
  const name = status ? (statusIcons[type] ?? icon) : icon;
  if (label === '' && name === '') {
    return null;
  }

  return (
    <span data-lathe-badge-type={type} className="inline-flex">
      <Badge tone={badgeTones[type] ?? 'neutral'}>
        {name !== '' && createElement(iconNamed(name), { 'aria-hidden': true, className: cn('size-3.5', type === 'loading' && 'animate-spin') })}
        {label}
      </Badge>
    </span>
  );
}

function badgeOf(cell: IndexFieldProps['cell'], status: boolean) {
  return <BadgeView label={cell.display} type={text(extra(cell, 'type'))} icon={text(extra(cell, 'icon'))} status={status} />;
}

export function BadgeIndex({ cell }: IndexFieldProps) {
  return badgeOf(cell, false);
}

export function StatusIndex({ cell }: IndexFieldProps) {
  return badgeOf(cell, true);
}

const iconColors: Record<string, string> = {
  info: 'text-info',
  success: 'text-success',
  warning: 'text-warning',
  danger: 'text-destructive',
  primary: 'text-primary',
  muted: 'text-muted-foreground',
};

function IconView({ source }: { source: IndexFieldProps['cell'] }) {
  const name = text(extra(source, 'icon'));

  if (name === '') {
    return null;
  }

  return (
    <span role="img" aria-label={source.display} data-lathe-icon={name} className={cn('inline-flex', iconColors[text(extra(source, 'color'))])}>
      {createElement(iconNamed(name), { 'aria-hidden': true, className: 'size-4' })}
    </span>
  );
}

export function IconIndex({ cell }: IndexFieldProps) {
  return <IconView source={cell} />;
}

const dash = <span className="text-muted-foreground">—</span>;

export function IconDetail({ field }: DetailFieldProps) {
  return text(extra(field, 'icon')) === '' ? dash : <IconView source={field} />;
}

function size(field: FieldSchema, key: 'width' | 'height', fallback: number): number {
  const value = field[key];

  return typeof value === 'number' ? value : fallback;
}

function SparklineView({ field, value }: { field: FieldSchema; value: unknown }) {
  const series = seriesOf(value);

  return series.length === 0 ? null : (
    <Sparkline series={series} width={size(field, 'width', 100)} height={size(field, 'height', 50)} bars={field.asBarChart === true} label={field.label} />
  );
}

export function SparklineIndex({ field, cell }: IndexFieldProps) {
  return <SparklineView field={field} value={cell.value} />;
}

export function SparklineDetail({ field }: DetailFieldProps) {
  return seriesOf(field.value).length === 0 ? dash : <SparklineView field={field} value={field.value} />;
}

const lineStyles: Record<string, string> = {
  heading: 'font-semibold text-foreground',
  subtitle: 'text-sm text-muted-foreground',
  small: 'text-xs text-muted-foreground',
  base: 'text-sm',
};

function LineView({ source }: { source: object }) {
  const display = text(extra(source, 'display'));

  return (
    <span className={cn('block', lineStyles[text(extra(source, 'style'))] ?? lineStyles.base, text(extra(source, 'extraClasses')))}>
      {display}
    </span>
  );
}

export function LineIndex({ cell }: IndexFieldProps) {
  return <LineView source={cell} />;
}

export function StackIndex({ cell }: IndexFieldProps) {
  const lines = Array.isArray(cell.value) ? cell.value.filter((line): line is object => typeof line === 'object' && line !== null) : [];

  return (
    <div className="flex flex-col">
      {lines.map((line, index) => (
        <LineView key={index} source={line} />
      ))}
    </div>
  );
}
