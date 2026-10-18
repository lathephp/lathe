import type { Column } from './types';

interface TableSummaryPlaceholderProps {
  columns: Column[];
  rows: number;
  /** The class of the checkbox column before the fields, which the footer leaves empty. */
  leading: string;
  /** Whether a row menu column follows the fields. */
  trailing: boolean;
}

/** The footer's place held while the summaries load: `rows` rows of the real height, then the real footer fades in. */
export function TableSummaryPlaceholder({ columns, rows, leading, trailing }: TableSummaryPlaceholderProps) {
  return (
    <tfoot aria-hidden="true">
      {Array.from({ length: rows }, (_, index) => (
        <tr key={index} className="h-10 border-t border-border">
          <td className={leading} />
          <td colSpan={columns.length + (trailing ? 1 : 0)} className="px-3">
            <span className="block h-3 w-24 animate-pulse rounded bg-skeleton motion-reduce:animate-none" />
          </td>
        </tr>
      ))}
    </tfoot>
  );
}
