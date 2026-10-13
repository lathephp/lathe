export type Translator = (key: string, replace?: Record<string, string | number>) => string;

/** English is the source text: the default translator only fills :placeholders. */
export const english: Translator = (key, replace = {}) =>
  Object.entries(replace).reduce((text, [name, value]) => text.replaceAll(`:${name}`, String(value)), key);

let translator: Translator = english;

/** Translate a string through the current translator. */
export const __: Translator = (key, replace) => translator(key, replace);

export function setTranslator(next: Translator): void {
  translator = next;
}
