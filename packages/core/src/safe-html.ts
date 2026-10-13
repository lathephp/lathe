declare const safeHtmlBrand: unique symbol;

/**
 * HTML the server has sanitized. On the wire it is `{ __html: string }`; the brand exists
 * only in the type, so a plain string or an unbranded object does not satisfy it.
 */
export type SafeHtml = { readonly __html: string } & { readonly [safeHtmlBrand]: true };
