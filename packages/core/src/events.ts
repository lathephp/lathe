type Listener = (payload?: unknown) => void;

const listeners = new Map<string, Set<Listener>>();

/** The global event bus. */
export function on(event: string, listener: Listener): () => void {
  const set = listeners.get(event) ?? new Set<Listener>();
  set.add(listener);
  listeners.set(event, set);

  return () => {
    off(event, listener);
  };
}

export function once(event: string, listener: Listener): () => void {
  const wrapped: Listener = (payload) => {
    off(event, wrapped);
    listener(payload);
  };

  return on(event, wrapped);
}

export function off(event: string, listener: Listener): void {
  listeners.get(event)?.delete(listener);
}

export function emit(event: string, payload?: unknown): void {
  [...(listeners.get(event) ?? [])].forEach((listener) => {
    listener(payload);
  });
}
