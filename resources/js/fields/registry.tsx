import { createElement, type ComponentType } from 'react';
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
      className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60 aria-[invalid]:border-destructive"
    />
  );
}

export function IndexField(props: IndexFieldProps) {
  return createElement(registries.index.get(props.field.component) ?? FallbackField, props);
}

export function DetailField(props: DetailFieldProps) {
  return createElement(registries.detail.get(props.field.component) ?? DetailFallbackField, props);
}

export function FormField(props: FormFieldProps) {
  return createElement(registries.form.get(props.field.component) ?? FormFallbackField, props);
}
