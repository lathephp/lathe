import type { FieldSchema } from '../types';

export interface Option {
  value: string;
  label: string;
  group?: string;
  color?: string;
  icon?: string;
}

function isOption(item: unknown): item is Option {
  return typeof item === 'object' && item !== null && 'value' in item && 'label' in item;
}

/** The options the server sent for a field. */
export function optionsOf(field: FieldSchema): Option[] {
  return Array.isArray(field.options) ? field.options.filter(isOption) : [];
}

/** The options whose label holds the query, plus the ones that stay whatever the query says. */
export function filterOptions(options: Option[], query: string, keep: string[]): Option[] {
  const needle = query.trim().toLowerCase();

  return needle === ''
    ? options
    : options.filter((option) => keep.includes(option.value) || option.label.toLowerCase().includes(needle));
}

/** The label of the option a value picks, or the value itself when none matches. */
export function labelOf(options: Option[], value: string): string {
  return options.find((option) => option.value === value)?.label ?? value;
}

function isScalar(item: unknown): item is string | number | boolean {
  return typeof item === 'string' || typeof item === 'number' || typeof item === 'boolean';
}

/** A value as a list of strings: an array keeps its scalar items, null gives none, a scalar gives itself. */
export function stringsOf(value: unknown): string[] {
  if (Array.isArray(value)) {
    return value.filter(isScalar).map(String);
  }

  return isScalar(value) ? [String(value)] : [];
}

/** A group value as a record of option to on or off; anything else is an empty group. */
export function groupState(value: unknown): Record<string, boolean> {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) {
    return {};
  }

  return Object.fromEntries(Object.entries(value).map(([key, on]) => [key, on === true]));
}

export const inputClass =
  'lathe-input';
