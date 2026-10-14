/** What a run's toast says while it runs: the verb of a bulk run. */
export type RunVerb = 'action' | 'delete' | 'forceDelete' | 'restore';

export type StartedRun = { kind: 'bulk'; id: string; verb: RunVerb } | { kind: 'export'; id: string } | { kind: 'import'; id: string };

let runs: StartedRun[] = [];
const listeners = new Set<() => void>();

function set(next: StartedRun[]): void {
  runs = next;
  listeners.forEach((listener) => {
    listener();
  });
}

/** The queued runs this page started, in the order they started. */
export const runStore = {
  get: (): StartedRun[] => runs,
  subscribe: (listener: () => void): (() => void) => {
    listeners.add(listener);

    return () => {
      listeners.delete(listener);
    };
  },
  dismiss: (kind: StartedRun['kind'], id: string): void => {
    set(runs.filter((run) => run.kind !== kind || run.id !== id));
  },
};

function detail(event: Event): Record<string, unknown> {
  return event instanceof CustomEvent && typeof event.detail === 'object' && event.detail !== null ? (event.detail as Record<string, unknown>) : {};
}

function add(run: StartedRun): void {
  set([...runs.filter((other) => other.kind !== run.kind || other.id !== run.id), run]);
}

/**
 * Records each run the page starts: lathe:bulk-run-started {id, verb}, lathe:export-started {id}
 * and lathe:import-started {id}. Returns the function that stops listening.
 */
export function listenForRuns(): () => void {
  const listeners: [string, (event: Event) => void][] = [
    [
      'lathe:bulk-run-started',
      (event) => {
        const { id, verb } = detail(event);
        add({ kind: 'bulk', id: String(id), verb: typeof verb === 'string' ? (verb as RunVerb) : 'action' });
      },
    ],
    [
      'lathe:export-started',
      (event) => {
        add({ kind: 'export', id: String(detail(event).id) });
      },
    ],
    [
      'lathe:import-started',
      (event) => {
        add({ kind: 'import', id: String(detail(event).id) });
      },
    ],
  ];

  for (const [name, listener] of listeners) {
    window.addEventListener(name, listener);
  }

  return () => {
    for (const [name, listener] of listeners) {
      window.removeEventListener(name, listener);
    }
  };
}
