import { formatNumber, type Translator } from '@lathephp/core';
import type { FilterValue } from './useIndexState';
import type { FilterOption, IndexFilter } from './types';

/** One piece of the filter bar after parsing: applied to the URL unless it carries an error. */
export interface FilterChip {
  /** The typed text, which a red chip shows. */
  token: string;
  /** The filter's key, or 'trashed'. */
  key: string;
  /** The value as the URL holds it. */
  value?: FilterValue;
  label: string;
  /** Why the chip is not applied. */
  error?: string;
}

export interface LookupResult {
  label: string;
  value: string | number;
}

export type ParsedToken = { chip: FilterChip } | { lookup: { filter: IndexFilter; term: string } };

/** Split the bar's text at spaces outside double quotes. */
function pieces(text: string): string[] {
  const found: string[] = [];
  let current = '';
  let quoted = false;

  for (const char of text) {
    if (char === '"') {
      quoted = !quoted;
    }

    if (/\s/.test(char) && !quoted) {
      if (current !== '') {
        found.push(current);
      }

      current = '';
    } else {
      current += char;
    }
  }

  return current === '' ? found : [...found, current];
}

/** key:value pieces become tokens; everything else, a quoted phrase included, stays search words. */
export function splitInput(text: string): { tokens: string[]; words: string } {
  const tokens: string[] = [];
  const words: string[] = [];

  for (const piece of pieces(text)) {
    (/^[^\s:"]+:./.test(piece) ? tokens : words).push(piece);
  }

  return { tokens, words: words.join(' ') };
}

const unquote = (text: string): string => text.trim().replace(/^"(.*)"$/, '$1');

/** The comma-separated values of a token, quotes removed and commas inside quotes kept. */
function values(raw: string): string[] {
  const found: string[] = [];
  let current = '';
  let quoted = false;

  for (const char of raw) {
    if (char === '"') {
      quoted = !quoted;
    } else if (char === ',' && !quoted) {
      found.push(current.trim());
      current = '';
    } else {
      current += char;
    }
  }

  return [...found, current.trim()];
}

const quote = (term: string): string => (/[\s,]/.test(term) ? `"${term}"` : term);

function findOption(filter: IndexFilter, typed: string) {
  const wanted = typed.toLowerCase();

  return filter.options?.find((option) => option.value.toLowerCase() === wanted || option.label.toLowerCase() === wanted);
}

function isNumber(text: string): boolean {
  return /^-?(\d+\.?\d*|\.\d+)$/.test(text);
}

function isDate(text: string): boolean {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(text)) {
    return false;
  }

  const [year, month, day] = text.split('-').map(Number);

  return new Date(Date.UTC(year ?? 0, (month ?? 1) - 1, day ?? 1)).toISOString().slice(0, 10) === text;
}

/** The two sides of >a, <a, a..b or a bare a, undefined when a side fails `valid`. */
function sides(text: string, valid: (side: string) => boolean): { low?: string; high?: string } | undefined {
  const [first, second, ...rest] = text.split('..');

  if (first !== undefined && second !== undefined) {
    return rest.length === 0 && valid(first) && valid(second) ? { low: first, high: second } : undefined;
  }

  if (text.startsWith('>')) {
    return valid(text.slice(1)) ? { low: text.slice(1) } : undefined;
  }

  if (text.startsWith('<')) {
    return valid(text.slice(1)) ? { high: text.slice(1) } : undefined;
  }

  return valid(text) ? { low: text, high: text } : undefined;
}

function sidesLabel(low: string | undefined, high: string | undefined): string {
  return low !== undefined && high !== undefined ? (low === high ? low : `${low} – ${high}`) : low !== undefined ? `≥ ${low}` : `≤ ${high ?? ''}`;
}

/** Turn one key:value token into a chip, or into a lookup the server must answer. */
export function parseToken(token: string, filters: IndexFilter[], options: { softDeletes: boolean }, __: Translator): ParsedToken {
  const colon = token.indexOf(':');
  const typedKey = token.slice(0, colon);
  const raw = token.slice(colon + 1);
  const filter = filters.find((candidate) => candidate.token.toLowerCase() === typedKey.toLowerCase());
  const fail = (key: string, error: string): ParsedToken => ({ chip: { token, key, label: token, error } });

  if (filter === undefined) {
    if (options.softDeletes && typedKey.toLowerCase() === 'trashed') {
      const value = unquote(raw).toLowerCase();

      return value === 'with' || value === 'only'
        ? { chip: { token, key: 'trashed', value, label: `${__('Trashed')}: ${value}` } }
        : fail('trashed', __('Trashed takes with or only'));
    }

    return fail(typedKey, __('No filter named :key', { key: typedKey }));
  }

  const name = filter.name;
  const chip = (value: FilterValue, shown: string): ParsedToken => ({ chip: { token, key: filter.key, value, label: `${name}: ${shown}` } });

  if (filter.kind === 'text') {
    const text = unquote(raw);

    return chip(text, text);
  }

  const typed = values(raw);
  const first = typed[0] ?? '';
  const several = filter.kind === 'multiple' || (filter.kind === 'boolean' && (filter.options?.length ?? 0) > 1);

  if (typed.length > 1 && !several) {
    return fail(filter.key, __(':filter takes one value', { filter: name }));
  }

  if (filter.kind === 'relation') {
    return { lookup: { filter, term: first } };
  }

  if (filter.kind === 'range') {
    const scale = filter.scale ?? 1;
    const found = sides(first, isNumber);

    if (found === undefined) {
      return fail(filter.key, __(':filter takes a number, >n, <n or a..b', { filter: name }));
    }

    const stored = (side: string | undefined) => (side === undefined ? undefined : String(Math.round(Number(side) * scale)));
    const low = stored(found.low);
    const high = stored(found.high);
    const shown = (side: string | undefined) => (side === undefined ? undefined : formatNumber(Number(side)));

    return chip({ ...(low === undefined ? {} : { min: low }), ...(high === undefined ? {} : { max: high }) }, sidesLabel(shown(found.low), shown(found.high)));
  }

  if (filter.kind === 'date') {
    const found = sides(first, isDate);

    if (found === undefined) {
      return fail(filter.key, __(':filter takes a date as YYYY-MM-DD, >date, <date or a..b', { filter: name }));
    }

    return chip({ ...(found.low === undefined ? {} : { from: found.low }), ...(found.high === undefined ? {} : { to: found.high }) }, sidesLabel(found.low, found.high));
  }

  const [only] = filter.options ?? [];

  if (filter.kind === 'boolean' && filter.options?.length === 1 && only !== undefined) {
    const answer = first.toLowerCase();

    if (answer !== 'yes' && answer !== 'no') {
      return fail(filter.key, __(':filter takes yes or no', { filter: name }));
    }

    return chip({ [only.value]: answer === 'yes' ? '1' : '0' }, answer === 'yes' ? __('Yes') : __('No'));
  }

  // select, multiple and a several-option boolean take options by value or label.
  const chosen: FilterOption[] = [];

  for (const item of typed) {
    const option = findOption(filter, item);

    if (option === undefined) {
      return fail(filter.key, __(':filter has no option :value', { filter: name, value: item }));
    }

    chosen.push(option);
  }

  const [one] = chosen;
  const value = filter.kind === 'boolean' || chosen.length > 1 || one === undefined ? chosen.map((option) => option.value) : one.value;

  return chip(value, chosen.map((option) => option.label).join(', '));
}

/** The chip for a relation token once the server has searched the filter's options for its title. */
export function chipFromResults(filter: IndexFilter, term: string, results: LookupResult[], __: Translator): FilterChip {
  const token = `${filter.token}:${quote(term)}`;
  const match = results.find((result) => result.label.toLowerCase() === term.toLowerCase()) ?? results[0];

  if (match === undefined) {
    return { token, key: filter.key, label: token, error: __('No :filter matches :value', { filter: filter.name, value: term }) };
  }

  return { token, key: filter.key, value: String(match.value), label: `${filter.name}: ${match.label}` };
}

/** The index URL's filter and trashed state for the chips; red chips are skipped and a later chip wins. */
export function toUrlState(chips: FilterChip[]): { filter: Record<string, FilterValue>; trashed?: string } {
  const state: { filter: Record<string, FilterValue>; trashed?: string } = { filter: {} };

  for (const chip of chips) {
    if (chip.error !== undefined || chip.value === undefined) {
      continue;
    }

    if (chip.key === 'trashed') {
      state.trashed = chip.value as string;
    } else {
      state.filter[chip.key] = chip.value;
    }
  }

  return state;
}
