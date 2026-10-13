import { afterEach, describe, expect, it } from 'vitest';
import { formatCurrency, formatDate, formatDateTime, formatNumber } from '../format';
import { sharedProps } from '../props';

// Newer ICU puts a narrow no-break space before AM/PM; compare with plain spaces.
const plain = (text: string) => text.replace(new RegExp('[\\u202f\\u00a0]', 'g'), ' ');

afterEach(() => {
  sharedProps.set({});
});

describe('formatCurrency', () => {
  it('shows a USD decimal string with its cents', () => {
    sharedProps.set({ locale: 'en' });

    expect(formatCurrency('1234.50', 'USD')).toBe('$1,234.50');
  });

  it('shows JPY without decimals', () => {
    sharedProps.set({ locale: 'en' });

    expect(formatCurrency('1234', 'JPY')).toBe('¥1,234');
  });

  it('keeps digits a float cannot hold', () => {
    sharedProps.set({ locale: 'en' });

    expect(formatCurrency('90071992547409.93', 'USD')).toBe('$90,071,992,547,409.93');
  });
});

describe('formatNumber', () => {
  it('groups with commas in en', () => {
    sharedProps.set({ locale: 'en' });

    expect(formatNumber(1234567.5)).toBe('1,234,567.5');
  });

  it('groups with spaces and a decimal comma in fr-CA', () => {
    sharedProps.set({ locale: 'fr-CA' });

    expect(plain(formatNumber(1234567.5))).toBe('1 234 567,5');
  });

  it('takes Intl options', () => {
    sharedProps.set({ locale: 'en' });

    expect(formatNumber(1234, { notation: 'compact' })).toBe('1.2K');
  });
});

describe('formatDate', () => {
  it('shows a Y-m-d date unshifted, whatever the timezone', () => {
    sharedProps.set({ locale: 'en', timezone: 'Pacific/Kiritimati' });

    expect(formatDate('2026-10-14')).toBe('Oct 14, 2026');
  });
});

describe('formatDateTime', () => {
  it('converts a UTC instant into the user timezone', () => {
    sharedProps.set({ locale: 'en', timezone: 'Pacific/Kiritimati' });

    expect(plain(formatDateTime('2026-10-13T19:00:00Z'))).toBe('Oct 14, 2026, 9:00 AM');
  });
});

describe('fallbacks', () => {
  it('uses en and UTC when the shared props carry neither', () => {
    expect(formatNumber(1234.5)).toBe('1,234.5');
    expect(plain(formatDateTime('2026-10-13T19:00:00Z'))).toBe('Oct 13, 2026, 7:00 PM');
  });
});
