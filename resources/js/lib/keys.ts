const listeners = new Set<() => void>();

/** Opens the keyboard shortcuts sheet from anywhere. */
export function showKeys(): void {
  listeners.forEach((listener) => {
    listener();
  });
}

/** The layout's host listens here; it returns the way to stop. */
export function onShowKeys(listener: () => void): () => void {
  listeners.add(listener);

  return () => {
    listeners.delete(listener);
  };
}
