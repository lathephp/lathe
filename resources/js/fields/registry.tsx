import { __panel } from '@lathephp/core';
import { createElement, type ComponentType, type ReactElement } from 'react';
import type { Cell, DetailFieldData, FieldSchema, FormFieldData } from './types';

export interface IndexFieldProps {
  field: FieldSchema;
  cell: Cell;
}

export interface DetailFieldProps {
  field: DetailFieldData;
}

export interface FormFieldProps {
  field: FormFieldData;
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

export interface FieldRenderers {
  index?: ComponentType<IndexFieldProps>;
  detail?: ComponentType<DetailFieldProps>;
  form?: ComponentType<FormFieldProps>;
}

/** The three registries: a field's component key to the component that draws it in each context. */
export const registries = {
  index: new Map<string, ComponentType<IndexFieldProps>>(),
  detail: new Map<string, ComponentType<DetailFieldProps>>(),
  form: new Map<string, ComponentType<FormFieldProps>>(),
};

/** Register the renderers of one component key; a later call replaces an entry. */
export function registerField(key: string, renderers: FieldRenderers): void {
  if (renderers.index !== undefined) registries.index.set(key, renderers.index);
  if (renderers.detail !== undefined) registries.detail.set(key, renderers.detail);
  if (renderers.form !== undefined) registries.form.set(key, renderers.form);
}

/** The display string the server formatted, as text: a cell never injects HTML. */
export function FallbackField({ cell }: IndexFieldProps) {
  return cell.display;
}

export function DetailFallbackField({ field }: DetailFieldProps) {
  return field.display === '' ? <span className="text-muted-foreground">—</span> : field.display;
}

export function stringValue(value: unknown): string {
  if (value === null || value === undefined) {
    return '';
  }

  if (typeof value === 'string') {
    return value;
  }

  return typeof value === 'number' || typeof value === 'boolean' ? String(value) : JSON.stringify(value);
}

/** A plain text input for a key with no form renderer. */
export function FormFallbackField({ field, value, onChange, id, describedBy, invalid, disabled }: FormFieldProps) {
  return (
    <input
      id={id}
      name={field.attribute}
      type="text"
      value={stringValue(value)}
      placeholder={field.placeholder ?? undefined}
      autoComplete={field.autocomplete ?? undefined}
      aria-describedby={describedBy}
      aria-invalid={invalid || undefined}
      aria-required={field.required === true || undefined}
      disabled={disabled}
      onChange={(event) => {
        onChange(event.target.value);
      }}
      className="lathe-input"
    />
  );
}

// Emails, numbers and code read left to right even inside a right-to-left panel.
const leftToRight = new Set(['email-field', 'number-field', 'currency-field', 'id-field', 'code-field']);

function keepLeftToRight(component: string, node: ReactElement): ReactElement {
  return leftToRight.has(component) ? <span dir="ltr">{node}</span> : node;
}

// Plugin modules register with core before the built-ins register, so a built-in keeps its key.
function renderer<C extends keyof FieldRenderers>(context: C, key: string) {
  return (registries[context].get(key) ?? __panel.fieldComponent(key)?.[context]) as FieldRenderers[C];
}

export function IndexField(props: IndexFieldProps) {
  return keepLeftToRight(props.field.component, createElement(renderer('index', props.field.component) ?? FallbackField, props));
}

export function DetailField(props: DetailFieldProps) {
  return keepLeftToRight(props.field.component, createElement(renderer('detail', props.field.component) ?? DetailFallbackField, props));
}

export function FormField(props: FormFieldProps) {
  return createElement(renderer('form', props.field.component) ?? FormFallbackField, props);
}
