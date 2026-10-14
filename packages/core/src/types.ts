import type { ActionModalProps, FilterProps, PageProps, RenderHookProps, WidgetProps } from './registry';
import type { SafeHtml } from './safe-html';

/** A rule from the server (Lathe\Field\Condition) that shows, requires or locks a field. */
type Condition =
  | { op: 'all' | 'any'; conditions: Condition[] }
  | { op: 'eq' | 'neq' | 'in' | 'notIn' | 'gt' | 'gte' | 'lt' | 'lte' | 'filled' | 'blank'; field: string; value: unknown };

/** A field as the server describes it: its schema, the same in every context. Each field type adds its own options. */
export interface FieldData {
  key: string;
  attribute: string;
  label: string;
  component: string;
  sortable: boolean;
  computed: boolean;
  nullable: boolean;
  stacked: boolean;
  fullWidth: boolean;
  columnSpan: number | null;
  indexWidth: number | null;
  borderless: boolean;
  compact: boolean;
  textAlign: 'left' | 'center' | 'right';
  placeholder: string | null;
  help: string | null;
  helpWidth: number | string | null;
  wrapping: boolean;
  section: string | null;
  autocomplete: string | null;
  readonly?: boolean;
  required?: boolean;
  visibleWhen?: Condition | null;
  requiredWhen?: Condition | null;
  readonlyWhen?: Condition | null;
  immutable?: boolean;
  meta: Record<string, unknown>;
  [option: string]: unknown;
}

/** A record's value of one field and the text shown when no component draws it. */
interface Cell {
  value: unknown;
  display: string;
}

/** The props an index cell renderer receives. */
export interface IndexFieldProps {
  field: FieldData;
  cell: Cell;
}

/** The props a detail renderer receives: the schema with the record's cell. */
export interface DetailFieldProps {
  field: FieldData & Cell;
}

/** The props a form input receives. */
export interface FormFieldProps {
  field: FieldData & { value: unknown };
  value: unknown;
  onChange: (value: unknown) => void;
  /** The input's id; its label points here. */
  id: string;
  /** The ids the input names in aria-describedby: its help and its error. */
  describedBy: string | undefined;
  invalid: boolean;
  disabled: boolean;
  /** The other values of the form, for a field that follows another. */
  values?: Record<string, unknown>;
}

export type FilterComponentProps = FilterProps;
export type WidgetComponentProps = WidgetProps;
/** The props of a widget a plugin draws itself; the same as a built-in card's. */
export type CustomWidgetComponentProps = WidgetProps;
export type ActionModalComponentProps = ActionModalProps;
export type CustomPageProps = PageProps;
export type RenderHookContext = RenderHookProps;

/** The options of renderHook(): resources limits a hook to those Resource uriKeys. */
export interface RenderHookOptions {
  resources?: string[];
}

/** A filter of a Resource, as the index payload sends it. */
export interface FilterData {
  key: string;
  name: string;
  /** select-filter, multi-select-filter, boolean-filter, range-filter or date-filter. */
  component: string;
  /** Null for a remote picker, which pages its options from the server. */
  options: { label: string; value: string }[] | null;
  remote: boolean;
  immediate: boolean;
  placement: string;
  default: unknown;
  /** A range filter's inputs show the stored value divided by this, such as 100 for cents. */
  scale?: number;
}

/** A card without its value, as Widget::descriptor() sends it. Each type adds its own options. */
export interface WidgetData {
  name: string;
  uriKey: string;
  component: string;
  width: string;
  columns: number;
  height: 'fixed' | 'double' | 'dynamic';
  help: string | null;
  helpWidth: number | string;
  refreshEvery: number;
  refreshWhenActionsRun: boolean;
  refreshWhenFiltersChange: boolean;
  onlyOnDetail: boolean;
  requestsValue: boolean;
  ranges: { value: number | string; label: string }[];
  [option: string]: unknown;
}

/** An action's menu metadata, as GET /actions and the index payload send it. */
export interface ActionData {
  uriKey: string;
  name: string;
  icon: string | null;
  description: string | null;
  group: string | null;
  destructive: boolean;
  standalone: boolean;
  sole: boolean;
  pinned: boolean;
  withoutConfirmation: boolean;
  confirmText: string | null;
  confirmButtonText: string;
  cancelButtonText: string;
  size: string;
  style: 'window' | 'fullscreen';
  asBlob: boolean;
  showOnIndex: boolean;
  showOnDetail: boolean;
  showOnTableRow: boolean;
  /** Set when the list was asked for one record (detail, table-row). */
  authorizedToRun?: boolean;
}

/** One row of an index table. */
export interface ResourceRow {
  id: number | string;
  title: string;
  cells: Record<string, Cell | undefined>;
  abilities: Record<string, boolean>;
  /** Sent only by a soft-deleting Resource. */
  trashed?: boolean;
  /** The actions the user may run on this row, by uriKey. */
  actions?: string[];
  /** The stale-edit token an inline save sends back; sent when a column is inline. */
  updatedAt?: string | null;
  /** Present while the rows are grouped. */
  group?: { key: string; title: string; description: string | null };
}

/** The Resource an index describes. */
export interface ResourceMeta {
  uriKey: string;
  label: string;
  singularLabel: string;
  perPageOptions: number[];
  searchable: boolean;
  softDeletes: boolean;
  abilities: { create: boolean };
  /** Rows get a preview button: some field shows in the Preview context. */
  preview?: boolean;
  definition: {
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
  };
}

/** A plugin of the panel: its name and the key of its page at /lathe/plugins/{uriKey}. */
export interface PluginMeta {
  uriKey: string;
  name: string;
}

/** A resource plugin on a detail page: the component key a resourcePlugin() call registered. */
export interface ResourcePluginLayout {
  type: 'resource-plugin';
  component: string;
  attribute: string;
}

export type BadgeType = 'info' | 'success' | 'warning' | 'danger';

/** A menu badge, as Lathe\Menu\Badge sends it. */
export interface BadgeData {
  value: string;
  type: BadgeType | 'destructive';
  tooltip?: string;
}

/** A panel of fields on a detail page or a form. */
export interface SectionLayout {
  type: 'panel';
  attribute: string;
  name: string;
  fields: string[];
  toolbar: boolean;
  collapsible: boolean;
  collapsed: boolean;
  hideHeading: boolean;
  headingInCard: boolean;
  help: SafeHtml | null;
  limit: number | null;
  columns: number | null;
  columnSpan: number | null;
  borderless: boolean;
}

export interface TabLayout {
  attribute: string;
  label: string;
  icon: string | null;
  badge: string | null;
  badgeType: BadgeType | null;
  columns: number | null;
  borderless: boolean;
  items: LayoutBlock[];
}

export interface TabGroupLayout {
  type: 'tab-group';
  attribute: string;
  label: string | null;
  queryKey: string;
  activeTab: number;
  vertical: boolean;
  contained: boolean;
  columnSpan: number | null;
  tabs: TabLayout[];
}

/** The panels, grids, tab groups and relation blocks of a detail page or a form. */
export type LayoutBlock =
  | SectionLayout
  | TabGroupLayout
  | { type: 'grid'; columns: number; items: LayoutBlock[] }
  | { type: 'relation-field' | `has-one-${string}`; key: string; field: FieldData };

/** One page of rows and where it sits. */
export interface PaginatedResponse<T> {
  table: { rows: T[] };
  state: { page: number; perPage: number; hasMorePages: boolean; sort: string };
}

/** What GET /lathe/api/resources/{resource} answers. */
export interface ResourceIndexResponse extends PaginatedResponse<ResourceRow> {
  resource: ResourceMeta;
  table: {
    columns: (FieldData & { sortKey: string; inline?: boolean })[];
    sortKeys: string[];
    rows: ResourceRow[];
    rowKey: string;
  };
  filters?: { available: FilterData[]; applied: Record<string, unknown> };
  actions?: { actions: ActionData[]; pivotActions: { name: string; actions: ActionData[] }[]; counts: Record<string, number> };
  [key: string]: unknown;
}

/** What GET /lathe/api/resources/{resource}/{id}/peek answers: the record and its fields. */
export interface ResourceDetailResponse {
  id: string | number;
  title: string;
  subtitle: string;
  avatar: { url: string | null; initials: string | null; rounded: boolean } | null;
  fields: (FieldData & Cell)[];
}

/** One row of the global search. `url` is relative to the panel. */
export interface GlobalSearchResult {
  resourceTitle: string;
  title: string;
  subTitle: string | null;
  resourceName: string;
  resourceId: string | number;
  avatar: string | null;
  rounded: boolean;
  url: string;
}

export interface NotificationData {
  id: string;
  icon: string | null;
  message: string;
  body: string | null;
  notificationType: 'success' | 'error' | 'warning' | 'info';
  actions: { text: string; url: string; icon: string | null; style: 'link' | 'button' | 'icon'; openInNewTab: boolean }[];
  duration: number | null;
  readAt: string | null;
  createdAtHuman: string;
}

