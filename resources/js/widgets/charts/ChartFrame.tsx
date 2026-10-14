import { useTranslation } from '@lathephp/core';
import type { ReactNode } from 'react';
import { cn } from '../../lib/cn';

export interface ChartFrameProps {
  /** The widget name, read out as the chart's name. */
  name: string;
  /** The header cells of the hidden table. */
  columns: string[];
  /** One row per point: its label, then its values. */
  rows: (string | number)[][];
  className?: string;
  children: ReactNode;
}

/**
 * A chart with a name for screen readers and its numbers in a visually hidden table,
 * since the SVG itself says nothing to assistive technology.
 */
export function ChartFrame({ name, columns, rows, className, children }: ChartFrameProps) {
  return (
    <div role="figure" aria-label={name} className={cn('relative h-full w-full', className)}>
      <div aria-hidden="true" className="h-full w-full">
        {children}
      </div>
      <div className="sr-only">
        <table>
          <thead>
            <tr>
              {columns.map((column, index) => (
                <th key={index} scope="col">
                  {column}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((row, index) => (
              <tr key={index}>
                {row.map((cell, column) => (column === 0 ? <th key={column} scope="row">{cell}</th> : <td key={column}>{cell}</td>))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

/** What a chart card shows when the server sent no points. */
export function NoData() {
  const { __ } = useTranslation();

  return <p className="text-sm font-medium text-muted-foreground">{__('No Data')}</p>;
}
