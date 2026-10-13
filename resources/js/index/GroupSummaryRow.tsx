import { useTranslation } from '@lathephp/core';
import { cn } from '../lib/cn';
import type { Column, SummaryValue } from './types';

interface GroupSummaryRowProps {
  columns: Column[];
  values: Record<string, SummaryValue[] | undefined>;
  /** The sticky class of the table's first column, so the row title stays with it. */
  firstColumn: string;
  /** The class of the checkbox column before the fields, which the subtotal leaves empty. */
  leading: string;
  /** Whether a row menu column follows the fields. */
  trailing: boolean;
}

/** The subtotal under a group, each total under its own column. */
export function GroupSummaryRow({ columns, values, firstColumn, leading, trailing }: GroupSummaryRowProps) {
  const { __ } = useTranslation();

  const totals = (column: Column) =>
    values[column.key]?.map((summary, index) => (
      <span key={index} className="block">
        {summary.label !== null && <span className="me-1 text-muted-foreground">{summary.label}</span>}
        {summary.value ?? '—'}
      </span>
    ));

  return (
    <tr className="h-10 border-b border-border font-medium">
      <td className={leading} />
      {columns.map((column, index) =>
        index === 0 ? (
          <th key={column.key} scope="row" className={cn('px-3 text-start whitespace-nowrap', firstColumn)}>
            {__('Subtotal')}
            {totals(column)}
          </th>
        ) : (
          <td key={column.key} className="px-3 whitespace-nowrap">
            {totals(column)}
          </td>
        ),
      )}
      {trailing && <td />}
    </tr>
  );
}
