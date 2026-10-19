import { formatNumber } from '@lathephp/core';
import { createElement } from 'react';
import { cn } from '../lib/cn';
import { tableIcon } from './tableIcons';
import type { WidgetRendererProps } from './types';

/** TableWidget::calculate(). */
export interface TablePayload {
  columns: { key: string; label: string; align?: 'left' | 'center' | 'right' }[];
  rows: Record<string, unknown>[];
  emptyText: string;
}

/** A MetricTableRow, as jsonSerialize() sends it. */
interface MetricRow {
  title?: string;
  subtitle?: string;
  icon?: string;
  iconClass?: string;
  actions?: { label: string; url?: string }[];
}

// Literal class names, so Tailwind finds them.
const aligns = { left: 'text-start', center: 'text-center', right: 'text-end tabular-nums' };

const isMetricRow = (row: Record<string, unknown>): row is Record<string, unknown> & MetricRow => 'title' in row || 'icon' in row || 'actions' in row;

function cell(value: unknown): string {
  if (value === null || value === undefined) {
    return '—';
  }

  if (typeof value === 'number') {
    return formatNumber(value);
  }

  return typeof value === 'string' ? value : JSON.stringify(value);
}

/** Rows of tabular data: plain cells per column, or a row with an icon, a title, a subtitle and links. */
export function TableCard({ value }: WidgetRendererProps) {
  const result = value as TablePayload | null;

  if (result === null || result.rows.length === 0) {
    return <p className="px-3 py-6 text-center text-sm text-muted-foreground md:px-5">{result?.emptyText}</p>;
  }

  return (
    // The card leaves the table its edges; the first and last cells pad it to the card's 20 px.
    <table className="w-full text-sm [&_td:first-child]:ps-3 [&_td:last-child]:pe-3 md:[&_td:first-child]:ps-5 md:[&_td:last-child]:pe-5 [&_th:first-child]:ps-3 [&_th:last-child]:pe-3 md:[&_th:first-child]:ps-5 md:[&_th:last-child]:pe-5">
      {result.columns.length > 0 && (
        <thead>
          <tr className="bg-muted">
            {result.columns.map((column) => (
              <th
                key={column.key}
                scope="col"
                className={cn('h-8 px-2 text-[11px] font-semibold tracking-[0.04em] text-muted-foreground uppercase', aligns[column.align ?? 'left'])}
              >
                {column.label}
              </th>
            ))}
          </tr>
        </thead>
      )}
      <tbody>
        {result.rows.map((row, index) =>
          isMetricRow(row) ? (
            <tr key={index} className="border-t border-border first:border-t-0">
              <td colSpan={result.columns.length || undefined} className="px-2 py-2">
                <div className="flex items-center gap-3">
                  {row.icon !== undefined && (
                    <span className={cn('shrink-0 text-muted-foreground', row.iconClass)}>
                      {createElement(tableIcon(row.icon), { 'aria-hidden': true, className: 'size-4' })}
                    </span>
                  )}
                  <div className="min-w-0 flex-1">
                    {row.title !== undefined && <div className="truncate font-medium">{row.title}</div>}
                    {row.subtitle !== undefined && <div className="truncate text-xs text-muted-foreground">{row.subtitle}</div>}
                  </div>
                  {row.actions !== undefined && row.actions.length > 0 && (
                    <div className="flex shrink-0 items-center gap-2">
                      {row.actions.map((action, link) =>
                        action.url === undefined ? (
                          <span key={link} className="text-xs text-muted-foreground">
                            {action.label}
                          </span>
                        ) : (
                          <a key={link} href={action.url} className="text-xs text-primary hover:underline">
                            {action.label}
                          </a>
                        ),
                      )}
                    </div>
                  )}
                </div>
              </td>
            </tr>
          ) : (
            <tr key={index} className="h-10 border-t border-border">
              {result.columns.map((column, at) => (
                <td key={column.key} className={cn('px-2 tabular-nums', at === 0 ? 'max-w-0 truncate font-medium' : 'w-px ps-10 whitespace-nowrap', aligns[column.align ?? 'left'])}>
                  {cell(row[column.key])}
                </td>
              ))}
            </tr>
          ),
        )}
      </tbody>
    </table>
  );
}
