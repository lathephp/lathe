import { useTranslation } from '@lathephp/core';
import { cn } from '../lib/cn';
import type { Column, Summaries, SummaryValue } from './types';

interface TableSummaryRowProps {
  columns: Column[];
  summaries: Summaries;
  /** The sticky class of the table's first column, so the row title stays with it. */
  firstColumn: string;
}

/** The footer rows "This page" and "All records", each total under its own column. */
export function TableSummaryRow({ columns, summaries, firstColumn }: TableSummaryRowProps) {
  const { __ } = useTranslation();
  const rowClass = 'h-10 border-t border-border font-medium';

  const totals = (values: SummaryValue[] | undefined) =>
    values?.map((summary, index) => (
      <span key={index} className="block">
        {summary.label !== null && <span className="me-1 text-muted-foreground">{summary.label}</span>}
        {summary.value ?? '—'}
      </span>
    ));

  const row = (title: string, values: Record<string, SummaryValue[] | undefined>) => (
    <tr className={rowClass}>
      {columns.map((column, index) =>
        index === 0 ? (
          <th key={column.key} scope="row" className={cn('px-3 text-start whitespace-nowrap', firstColumn)}>
            {title}
            {totals(values[column.key])}
          </th>
        ) : (
          <td key={column.key} className="px-3 whitespace-nowrap">
            {totals(values[column.key])}
          </td>
        ),
      )}
    </tr>
  );

  return (
    <tfoot>
      {summaries.page !== null && row(__('This page'), summaries.page)}
      {summaries.allRecords !== null && row(__('All records'), summaries.allRecords)}
      {summaries.allRecords === null && summaries.message !== null && (
        <tr className={rowClass}>
          <th scope="row" className={cn('px-3 text-start whitespace-nowrap', firstColumn)}>
            {__('All records')}
          </th>
          <td colSpan={Math.max(columns.length - 1, 1)} className="px-3 text-muted-foreground">
            {summaries.message}
          </td>
        </tr>
      )}
    </tfoot>
  );
}
