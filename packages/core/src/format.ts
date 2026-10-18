import { sharedProps } from './props';

// Building an Intl formatter is slow, so each locale and options pair builds one.
const numberFormats = new Map<string, Intl.NumberFormat>();
const dateFormats = new Map<string, Intl.DateTimeFormat>();

function locale(): string {
  const value = sharedProps.get().locale;

  return typeof value === 'string' && value !== '' ? value : 'en';
}

function timezone(): string {
  const value = sharedProps.get().timezone;

  return typeof value === 'string' && value !== '' ? value : 'UTC';
}

function numberFormat(options: Intl.NumberFormatOptions): Intl.NumberFormat {
  const key = `${locale()}|${JSON.stringify(options)}`;
  let format = numberFormats.get(key);

  if (!format) {
    format = new Intl.NumberFormat(locale(), options);
    numberFormats.set(key, format);
  }

  return format;
}

function dateFormat(options: Intl.DateTimeFormatOptions): Intl.DateTimeFormat {
  const key = `${locale()}|${JSON.stringify(options)}`;
  let format = dateFormats.get(key);

  if (!format) {
    format = new Intl.DateTimeFormat(locale(), options);
    dateFormats.set(key, format);
  }

  return format;
}

/** A number in the user's locale, such as 1,234.5 in en and 1 234,5 in fr-CA. */
export function formatNumber(value: number | string, options: Intl.NumberFormatOptions = {}): string {
  // Intl.NumberFormat reads a decimal string exactly, which a number cannot hold past 15 digits.
  return numberFormat(options).format(value as number);
}

/** Money from a decimal string such as "1234.50", with the currency's own digits. */
export function formatCurrency(amount: string | number, currency: string, options: Intl.NumberFormatOptions = {}): string {
  return formatNumber(amount, { ...options, style: 'currency', currency });
}

/** A Y-m-d date. It has no time, so no timezone moves it. */
export function formatDate(iso: string, options: Intl.DateTimeFormatOptions = {}): string {
  return dateFormat({ dateStyle: 'medium', ...options, timeZone: 'UTC' }).format(new Date(`${iso.slice(0, 10)}T00:00:00Z`));
}

/** A UTC ISO 8601 instant, shown in the user's timezone on the 24-hour clock: Oct 17, 2026, 23:12. */
export function formatDateTime(iso: string, options: Intl.DateTimeFormatOptions = {}): string {
  return dateFormat({ dateStyle: 'medium', timeStyle: 'short', hourCycle: 'h23', ...options, timeZone: timezone() }).format(new Date(iso));
}
