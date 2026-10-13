import type { Cell } from '../table/cells';
import type { FilterValue } from './useIndexState';

export interface Column {
  key: string;
  label: string;
  component: string;
  sortable: boolean;
  /** The token this column answers to in ?sort=, `customer.name` for the `customer` cell. */
  sortKey: string;
}

export interface LensEntry {
  uriKey: string;
  name: string;
}

export interface Row {
  id: number | string;
  title: string;
  cells: Record<string, Cell | undefined>;
  abilities: Record<string, boolean>;
  /** Sent only by a soft-deleting Resource. */
  trashed?: boolean;
  /** Present while the rows are grouped. */
  group?: RowGroup;
}

export interface FilterOption {
  label: string;
  value: string;
}

export interface AvailableFilter {
  key: string;
  name: string;
  /** select-filter, multi-select-filter, boolean-filter, range-filter or date-filter. */
  component: string;
  /** Null for a remote picker, which pages its options from the server. */
  options: FilterOption[] | null;
  remote: boolean;
  immediate: boolean;
  placement: string;
  default: unknown;
  /** A range filter's inputs show the stored value divided by this, such as 100 for cents. */
  scale?: number;
}

/** What the panel hands each filter control, keyed by the server's component name. */
export interface FilterControlProps {
  filter: AvailableFilter;
  value: FilterValue | undefined;
  onChange: (value: FilterValue) => void;
  /** The filters endpoint of the Resource; a remote picker reads `{endpoint}/{key}/options`. */
  endpoint: string;
  resource: string;
}

export interface ResourceDefinition {
  debounce: number;
  paginationType: 'links' | 'simple' | 'load-more';
  clickAction: 'detail' | 'edit' | 'select' | 'preview' | 'ignore';
  /** Seconds between table reloads, null for none. */
  polling: number | null;
  showPollingToggle: boolean;
  tableStyle: 'default' | 'tight';
  showColumnBorders: boolean;
  softDeletes: boolean;
  savedTabs: boolean;
}

export interface RowGroup {
  /** One stable string per group value; `__lathe_null__` for null. */
  key: string;
  title: string;
  description: string | null;
}

export interface Groups {
  available: { id: string; label: string }[];
  active: { id: string; direction: 'asc' | 'desc'; collapsible: boolean } | null;
}

export interface IndexProps {
  resource: {
    uriKey: string;
    label: string;
    singularLabel: string;
    perPageOptions: number[];
    searchable: boolean;
    softDeletes: boolean;
    abilities: { create: boolean };
    definition: ResourceDefinition;
  };
  table: { columns: Column[]; sortKeys: string[]; rows: Row[]; rowKey: string };
  /** The open lens; null on the plain index. */
  lens?: LensEntry | null;
  /** The lenses the user may see. */
  lenses?: LensEntry[];
  state: { page: number; perPage: number; hasMorePages: boolean; sort: string };
  filters?: { available: AvailableFilter[]; applied: Record<string, unknown> };
  /** Filter keys in the URL that the Resource no longer declares; the server dropped them. */
  staleFilters?: string[];
  /** Null when the Resource counts nothing. */
  count?: Count | null;
  /** A bad URL parameter: the page shows its message in place of the rows. */
  error?: { key: string; message: string };
  /** Deferred; null when no field is summarized. */
  summaries?: Summaries | null;
  /** The page for a bad URL parameter sends no saved tabs. */
  tabs?: Tabs;
  /** Deferred: the badges keyed by tab key, and by `saved:{id}` for a saved tab. */
  tabCounts?: Record<string, TabCount | undefined>;
  /** Missing on the error page, which reads no rows. */
  groups?: Groups;
}

export type BadgeType = 'info' | 'success' | 'warning' | 'danger';

export interface Tab {
  key: string;
  name: string;
  icon: string | null;
  badge: string | number | null;
  badgeType: BadgeType | null;
}

export interface SavedTab {
  id: number;
  name: string;
  badgeType: BadgeType | null;
  showCount: boolean;
  /** The Resource's filters or sorts changed since the save; filters and sort are what still applies. */
  stale: boolean;
  filters: Record<string, unknown>;
  sort: string | null;
}

export interface Tabs {
  items: Tab[];
  active: string | null;
  saved?: SavedTab[];
  activeSaved?: number | null;
}

export interface TabCount {
  count?: Count;
  badge?: string | number | null;
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
  /** The subtotals of the groups on the page, by group key, while the rows are grouped. */
  groups?: Record<string, Record<string, SummaryValue[] | undefined> | undefined>;
}

export interface Count {
  value: number;
  exact: boolean;
  capped: boolean;
  estimated: boolean;
}
