import type { Cell } from '../table/cells';

export interface Column {
  key: string;
  label: string;
  component: string;
  sortable: boolean;
  /** The token this column answers to in ?sort=, `customer.name` for the `customer` cell. */
  sortKey: string;
}

export interface Row {
  id: number | string;
  title: string;
  cells: Record<string, Cell | undefined>;
  abilities: Record<string, boolean>;
}

export interface IndexProps {
  resource: {
    uriKey: string;
    label: string;
    singularLabel: string;
    perPageOptions: number[];
    searchable: boolean;
    abilities: { create: boolean };
    definition: { debounce: number };
  };
  table: { columns: Column[]; sortKeys: string[]; rows: Row[]; rowKey: string };
  state: { page: number; perPage: number; hasMorePages: boolean; sort: string };
  /** Null when the Resource counts nothing. */
  count?: Count | null;
  /** A bad URL parameter: the page shows its message in place of the rows. */
  error?: { key: string; message: string };
  /** Deferred; null when no field is summarized. */
  summaries?: Summaries | null;
}

export interface SummaryValue {
  /** Null when the summary hides its label. */
  label: string | null;
  value: string | null;
}

/** Footer totals keyed by column. allRecords is null above the count cap, page when one page holds every row. */
export interface Summaries {
  allRecords: Record<string, SummaryValue[] | undefined> | null;
  page: Record<string, SummaryValue[] | undefined> | null;
  message: string | null;
}

export interface Count {
  value: number;
  exact: boolean;
  capped: boolean;
  estimated: boolean;
}
