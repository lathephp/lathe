import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

/** Joins class names, and a later Tailwind class wins over an earlier one it conflicts with. */
export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}
