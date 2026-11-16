import { CSPProvider } from '@base-ui/react/csp-provider';
import type { ReactNode } from 'react';

let nonce: string | null | undefined;

/** The server's CSP nonce, read once off the panel's entry script, which carries it. */
export function pageNonce(): string | undefined {
  if (nonce === undefined) {
    const read = document.querySelector<HTMLScriptElement>('script[type="module"][nonce]')?.nonce;
    nonce = read === undefined || read === '' ? null : read;
  }

  return nonce ?? undefined;
}

/** Gives the style tags Base UI injects inside it the page's nonce, so the panel's CSP lets them apply. */
export function PageNonce({ children }: { children: ReactNode }) {
  const value = pageNonce();

  return value === undefined ? children : <CSPProvider nonce={value}>{children}</CSPProvider>;
}
