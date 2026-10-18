import { useTranslation } from '@lathephp/core';
import { cn } from '../lib/cn';
import type { Column, ColumnPin, SummaryValue } from './types';

interface GroupSummaryRowProps {
  columns: Column[];
  values: Record<string, SummaryValue[] | undefined>;
  /** Each column's sticky class and offset, so the title and the pinned totals stay with their columns. */
  pins: ColumnPin[];
  /** The class of the checkbox column before the fields, which the subtotal leaves empty. */
  leading: string;
  /** Whether a row menu column follows the fields. */
  trailing: boolean;
}

/** The subtotal under a group, each total under its own column. */
export function GroupSummaryRow({ columns, values, pins, leading, trailing }: GroupSummaryRowProps) {
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
          <th key={column.key} scope="row" className={cn('px-3 text-start whitespace-nowrap', pins[0]?.className)}>
            {__('Subtotal')}
            {totals(column)}
          </th>
        ) : (
          <td key={column.key} className={cn('px-3 whitespace-nowrap', pins[index]?.className)} style={pins[index]?.style}>
            {totals(column)}
          </td>
        ),
      )}
      {trailing && <td />}
    </tr>
  );
}
