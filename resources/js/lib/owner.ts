/** A short tag for a signed-in user, so browser storage never holds their email; '' when signed out. */
function tagOf(email: string | null): string {
  if (email === null) {
    return '';
  }

  let hash = 5381;

  for (let index = 0; index < email.length; index++) {
    hash = ((hash * 33) ^ email.charCodeAt(index)) >>> 0;
  }

  return hash.toString(36);
}

/**
 * Hands what a browser storage keeps for one user to the user signed in now: when that is nobody,
 * or someone other than the user the storage last recorded under `key`, clear() empties it first.
 * Blocked storage holds nothing to hand over.
 */
export function claimStorage(storage: () => Storage, key: string, email: string | null, clear: (store: Storage) => void): void {
  try {
    const store = storage();
    const tag = tagOf(email);
    const last = store.getItem(key);

    if (tag === '' || (last !== null && last !== tag)) {
      clear(store);
    }

    if (tag === '') {
      store.removeItem(key);
    } else {
      store.setItem(key, tag);
    }
  } catch {
    // Blocked storage keeps nothing.
  }
}

/** Removes every key of the storage that starts with the prefix. */
export function removeKeys(store: Storage, prefix: string): void {
  const keys = Array.from({ length: store.length }, (_, index) => store.key(index)).filter((key): key is string => key?.startsWith(prefix) === true);

  for (const key of keys) {
    store.removeItem(key);
  }
}
