import type { ActionList } from '../actions/types';
import type { SafeHtml } from '@lathephp/core';
import type { ConditionData } from './conditions';

/** A field as the server describes it: its schema, the same in every context. */
export interface FieldSchema {
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
  visibleWhen?: ConditionData | null;
  requiredWhen?: ConditionData | null;
  readonlyWhen?: ConditionData | null;
  immutable?: boolean;
  meta: Record<string, unknown>;
  [option: string]: unknown;
}

/** An index cell: the value and the text shown when no component draws it. */
export interface Cell {
  value: unknown;
  display: string;
}

/** A field on the detail page: its schema plus the record's cell. */
export type DetailFieldData = FieldSchema & Cell;

/** A field on a form: its schema plus the starting value. */
export type FormFieldData = FieldSchema & { value: unknown };

export interface PanelBlock {
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

export interface GridBlock {
  type: 'grid';
  columns: number;
  items: LayoutBlock[];
}

export interface TabData {
  attribute: string;
  label: string;
  icon: string | null;
  badge: string | null;
  badgeType: 'info' | 'success' | 'warning' | 'danger' | null;
  columns: number | null;
  borderless: boolean;
  items: LayoutBlock[];
}

export interface TabGroupBlock {
  type: 'tab-group';
  attribute: string;
  label: string | null;
  queryKey: string;
  activeTab: number;
  vertical: boolean;
  contained: boolean;
  columnSpan: number | null;
  tabs: TabData[];
}

/** A relation panel field or a hasOne field, drawn as a block of its own on detail pages. */
export interface RelationBlock {
  type: 'relation-field' | `has-one-${string}`;
  key: string;
  field: FieldSchema;
}

/** The schema of a relation field, as a relation block carries it. */
export interface RelationFieldSchema extends FieldSchema {
  kind: string;
  relationship: string;
  /** The related Resource's uriKey. */
  resource: string;
  singularLabel: string;
  pluralLabel: string;
  collapsible: boolean;
  collapsed: boolean;
  withCount: boolean;
  perPageOptions: number[] | null;
}

export type LayoutBlock = PanelBlock | GridBlock | TabGroupBlock | RelationBlock;

export interface Breadcrumb {
  label: string;
  href: string | null;
}

/** The part of the index payload that describes the Resource. */
export interface RecordResource {
  uriKey: string;
  label: string;
  singularLabel: string;
  abilities: { create: boolean };
  [key: string]: unknown;
}

export interface DetailRecord {
  id: string | number;
  title: string;
  subtitle: string;
  avatar: { url: string | null; initials: string | null; rounded: boolean } | null;
  fields: DetailFieldData[];
  layout: LayoutBlock[];
  abilities: { view: boolean; update: boolean; delete: boolean; replicate: boolean; restore: boolean; forceDelete: boolean };
  trashed: boolean;
}

/** The detail page props. The record nests so no key shadows a shared prop such as layout. */
export interface DetailProps {
  resource: RecordResource;
  record: DetailRecord;
  breadcrumbs: Breadcrumb[];
  /** The actions shown on detail, each flagged for this record. */
  actions?: ActionList;
}

export interface FormPayload {
  fields: FormFieldData[];
  layout: LayoutBlock[];
  defaults?: Record<string, unknown>;
  borderless?: boolean;
  updatedAt?: string | null;
}

export interface FormProps {
  resource: RecordResource;
  mode: 'create' | 'update' | 'replicate';
  id: string | number | null;
  title: string;
  breadcrumbs: Breadcrumb[];
  form: FormPayload;
  submitLabel: string;
}
