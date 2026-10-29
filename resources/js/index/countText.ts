import { formatNumber } from '@lathephp/core';
import type { Count } from './types';

// An estimate is the database's guess for the table, so it reads as a rounded figure.
export function countText(count: Count): string {
  if (count.estimated) {
    return `~${formatNumber(count.value, { notation: 'compact', maximumFractionDigits: 1 })}`;
  }

  return `${formatNumber(count.value)}${count.capped ? '+' : ''}`;
}
