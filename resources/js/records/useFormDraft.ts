import { toast, useTranslation } from '@lathephp/core';
import { useEffect, useRef, useState } from 'react';
import type { FormFieldData } from '../fields/types';
import { claimStorage, removeKeys } from '../lib/owner';

/** Components a draft never holds: a file, and a password, which must never sit in the browser. */
const UNKEPT_COMPONENTS = new Set(['file-field', 'image-field', 'audio-field', 'avatar-field', 'password-field']);

/** The attributes a draft keeps: every field but the file and password ones. */
export function persistable(fields: FormFieldData[]): string[] {
  return fields.filter((field) => !UNKEPT_COMPONENTS.has(field.component)).map((field) => field.attribute);
}

const prefix = 'lathe-draft:';

function storageKey(): string {
  return `${prefix}${window.location.pathname}`;
}

/** Drops this tab's drafts when the signed-in user is not the one who typed them. */
export function claimDrafts(email: string | null): void {
  claimStorage(() => window.sessionStorage, 'lathe-draft-owner', email, (store) => {
    removeKeys(store, prefix);
  });
}

/**
 * The draft of this form's page, keeping only the given attributes; null when there is none, or
 * when another user than `email` typed it.
 */
export function readDraft(attributes: string[], email: string | null): Record<string, unknown> | null {
  claimDrafts(email);

  try {
    const raw = window.sessionStorage.getItem(storageKey());

    if (raw === null) {
      return null;
    }

    const saved = JSON.parse(raw) as Record<string, unknown>;

    return Object.fromEntries(attributes.filter((key) => key in saved).map((key) => [key, saved[key]]));
  } catch {
    return null;
  }
}

/**
 * Keep what the user typed in sessionStorage 500 ms after the last change. The caller starts
 * from readDraft(); this toasts "Draft restored" once when it did.
 */
export function useFormDraft(values: Record<string, unknown>, attributes: string[], restoredAtStart: boolean) {
  const { __ } = useTranslation();
  const [restored, setRestored] = useState(restoredAtStart);
  /** Only what the user typed becomes a draft, not values the form set itself. */
  const touched = useRef(false);

  useEffect(() => {
    if (restoredAtStart) {
      toast.info(__('Draft restored'));
    }
    // Toast once, when the form opens.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (!touched.current) {
      return undefined;
    }

    const timer = setTimeout(() => {
      try {
        window.sessionStorage.setItem(storageKey(), JSON.stringify(Object.fromEntries(attributes.map((key) => [key, values[key]]))));
      } catch {
        // A full or blocked storage keeps no draft.
      }
    }, 500);

    return () => {
      clearTimeout(timer);
    };
  }, [values, attributes]);

  return {
    restored,
    touch: () => {
      touched.current = true;
    },
    clear: () => {
      touched.current = false;
      setRestored(false);

      try {
        window.sessionStorage.removeItem(storageKey());
      } catch {
        // Nothing to clear.
      }
    },
  };
}
