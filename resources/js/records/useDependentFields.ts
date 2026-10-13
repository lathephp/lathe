import { request, toast, useLathe, useTranslation } from '@lathephp/core';
import { useEffect, useRef, useState } from 'react';
import type { FormFieldData } from '../fields/types';

/** What the server changed on one field for this form. */
export interface FieldPatchData {
  visible?: boolean;
  value?: unknown;
  options?: { value: string; label: string }[];
  readonly?: boolean;
  required?: boolean;
}

interface LookupReply {
  patches: Record<string, FieldPatchData>;
  changed: Record<string, unknown>;
}

/** The browser waits this long after the last change before it asks. */
export const DEPENDENCY_DEBOUNCE = 300;

/** At most this many fields per lookup. */
const MAX_FIELDS = 20;

interface Options {
  uriKey: string;
  mode: 'create' | 'update' | 'replicate';
  id: string | number | null;
  fields: FormFieldData[];
  values: Record<string, unknown>;
  /** The attributes the user typed in; the server never overwrites them. */
  dirty: ReadonlySet<string>;
  /** Apply the values the server set, without marking them typed. */
  apply: (values: Record<string, unknown>) => void;
}

/**
 * Ask the server about the fields whose dependsOn watches a value that just changed, 300 ms
 * after the last change. A failed lookup keeps every field as it was and toasts with Retry.
 */
export function useDependentFields({ uriKey, mode, id, fields, values, dirty, apply }: Options): Record<string, FieldPatchData> {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const [patches, setPatches] = useState<Record<string, FieldPatchData>>({});
  const previous = useRef(values);
  const latest = useRef({ fields, dirty, apply });

  useEffect(() => {
    latest.current = { fields, dirty, apply };
  });

  useEffect(() => {
    const before = previous.current;
    previous.current = values;
    const moved = Object.keys(values).filter((attribute) => values[attribute] !== before[attribute]);
    const targets = latest.current.fields
      .filter((field) => Array.isArray(field.dependsOn) && (field.dependsOn as string[]).some((attribute) => moved.includes(attribute)))
      .map((field) => field.key)
      .slice(0, MAX_FIELDS);

    if (targets.length === 0) {
      return undefined;
    }

    function lookup() {
      const byKey = new Map(latest.current.fields.map((field) => [field.key, field.attribute]));

      request<LookupReply>(`${apiPath}/resources/${uriKey}/field-dependencies`, {
        method: 'POST',
        body: { fields: targets, context: mode === 'update' ? 'update' : 'create', values, dirty: [...latest.current.dirty], id: mode === 'update' ? id : null },
      }).then(
        (reply) => {
          if (reply === null) {
            return;
          }

          setPatches((current) => ({ ...current, ...reply.patches }));
          latest.current.apply(Object.fromEntries(Object.entries(reply.changed).map(([key, value]) => [byKey.get(key) ?? key, value])));
        },
        () => {
          toast.error(__('Something went wrong. Please try again.'), { action: { label: __('Retry'), onClick: lookup } });
        },
      );
    }

    const timer = setTimeout(lookup, DEPENDENCY_DEBOUNCE);

    return () => {
      clearTimeout(timer);
    };
  }, [values, apiPath, uriKey, mode, id, __]);

  return patches;
}
