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

/**
 * Whether a key press belongs to an IME composition: the Enter that confirms the text, or an arrow
 * that moves through its candidates, is the IME's and the page must not act on it.
 */
export function composing(event: { nativeEvent: KeyboardEvent } | KeyboardEvent): boolean {
  return ('nativeEvent' in event ? event.nativeEvent : event).isComposing;
}
