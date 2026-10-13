import type { Cell } from '../table/cells';

export interface Column {
  key: string;
  label: string;
  component: string;
}

export interface Row {
  id: number | string;
  title: string;
  cells: Record<string, Cell | undefined>;
  abilities: Record<string, boolean>;
}

export interface IndexProps {
  resource: { uriKey: string; label: string; singularLabel: string; perPageOptions: number[] };
  table: { columns: Column[]; rows: Row[]; rowKey: string };
  state: { page: number; perPage: number; hasMorePages: boolean };
  /** Null when the Resource counts nothing. */
  count?: Count | null;
}

export interface Count {
  value: number;
  exact: boolean;
  capped: boolean;
  estimated: boolean;
}
