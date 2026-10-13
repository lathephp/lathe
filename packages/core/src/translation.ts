import type { SharedData } from './props';

export type Translator = (key: string, replace?: Record<string, string | number>) => string;

const ucfirst = (text: string): string => text.charAt(0).toUpperCase() + text.slice(1);

/** Fill :name as given, :NAME upper-cased and :Name with a capital first letter. */
function fill(text: string, replace: Record<string, string | number>): string {
  return (
    Object.entries(replace)
      // Longest name first, as Laravel does, so :to never replaces the start of :total.
      .sort(([a], [b]) => b.length - a.length)
      .reduce((out, [name, value]) => {
        const given = String(value);

        return out
          .replaceAll(`:${name}`, given)
          .replaceAll(`:${name.toUpperCase()}`, given.toUpperCase())
          .replaceAll(`:${ucfirst(name)}`, ucfirst(given));
      }, text)
  );
}

/** English is the source text: the default translator only fills :placeholders. */
export const english: Translator = (key, replace = {}) => fill(key, replace);

/** A translator over catalogues keyed by locale: `fr-CA`, then `fr`, then `en`; a missing key stays as written. */
export function createTranslator(locale: string, catalogues: Record<string, Record<string, string>>): Translator {
  const order = [locale, locale.split('-')[0] ?? locale, 'en'];

  return (key, replace = {}) => {
    const found = order.map((name) => catalogues[name]?.[key]).find((text) => text !== undefined);

    return fill(found ?? key, replace);
  };
}

let translator: Translator = english;

/** Translate a string through the current translator. */
export const __: Translator = (key, replace) => translator(key, replace);

export function setTranslator(next: Translator): void {
  translator = next;
}

/** The translator for the catalogue and locale in the shared data; English until they arrive. */
export function translatorFor(data: SharedData): Translator {
  const { locale, translations } = data;

  return typeof locale === 'string' && typeof translations === 'object' && translations !== null
    ? createTranslator(locale, { [locale]: translations as Record<string, string> })
    : english;
}
