import { formatNumber } from '@lathephp/core';
import type { NumberDisplay } from './types';

const compact: Intl.NumberFormatOptions = { notation: 'compact', maximumFractionDigits: 1 };

/** Intl options for a result's format: 'a' is compact, '.2' or '.00' is that many fraction digits. */
function options(value: number, format: NumberDisplay['format']): Intl.NumberFormatOptions {
  if (format !== null && typeof format === 'object') {
    return format;
  }

  if (format === 'a') {
    return compact;
  }

  const digits = typeof format === 'string' ? /^\.(\d+)$/.exec(format)?.[1] : undefined;

  if (digits !== undefined) {
    const count = /^0+$/.test(digits) ? digits.length : Number(digits);

    return { minimumFractionDigits: count, maximumFractionDigits: count };
  }

  return Math.abs(value) < 10_000 ? { maximumFractionDigits: 2 } : compact;
}

/** The suffix gets an English s unless the value is 1 or -1 or the suffix already ends in s. */
function suffixFor(value: number, display: NumberDisplay): string {
  const { suffix } = display;

  if (suffix === null || suffix === '') {
    return '';
  }

  const plain = !display.suffixInflection || Math.abs(value) === 1 || suffix.endsWith('s');

  return ` ${plain ? suffix : `${suffix}s`}`;
}

/** A result's number with its prefix and suffix, in the user's locale; an em dash when there is none. */
export function formatMetric(value: number | null, display: NumberDisplay): string {
  if (value === null || !Number.isFinite(value)) {
    return '—';
  }

  return `${display.prefix ?? ''}${formatNumber(value, options(value, display.format))}${suffixFor(value, display)}`;
}

/**
 * A chart's number formatter: the result's prefix, suffix and format when it sets one,
 * otherwise the plain locale number.
 */
export function chartFormatter(display: Partial<NumberDisplay> | null): (value: number) => string {
  const { prefix = null, suffix = null, suffixInflection = true, format = null } = display ?? {};

  if (prefix === null && suffix === null && format === null) {
    return (value) => formatNumber(value);
  }

  return (value) => formatMetric(value, { prefix, suffix, suffixInflection, format });
}
