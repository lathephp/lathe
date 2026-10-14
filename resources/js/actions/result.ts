import { toast } from '@lathephp/core';

/** What POST /action answers in `result`: one optional message and at most one effect. */
export interface ActionResult {
  message: string | null;
  level: 'success' | 'danger';
  effect:
    | null
    | { type: 'redirect'; url: string; newTab: boolean }
    | { type: 'visit'; path: string; options: Record<string, unknown> }
    | { type: 'download'; name: string; url: string }
    | { type: 'modal'; component: string; data: Record<string, unknown> }
    | { type: 'emit'; event: string; data: Record<string, unknown> }
    | { type: 'deleted' };
}

export interface ActionResultHandlers {
  openModal: (component: string, data: Record<string, unknown>) => void;
  /** Reloads the rows, after a run deleted some. */
  refresh: () => void;
  visit: (path: string, options: Record<string, unknown>) => void;
}

/** Shows a run's message and carries out its effect. */
export function applyActionResult(result: ActionResult, on: ActionResultHandlers): void {
  if (result.message !== null) {
    if (result.level === 'danger') {
      toast.error(result.message);
    } else {
      toast.success(result.message);
    }
  }

  const effect = result.effect;

  switch (effect?.type) {
    case 'redirect':
      if (effect.newTab) {
        window.open(effect.url, '_blank', 'noopener');
      } else {
        window.location.assign(effect.url);
      }
      break;
    case 'visit':
      on.visit(effect.path, effect.options);
      break;
    case 'download': {
      const anchor = document.createElement('a');
      anchor.href = effect.url;
      anchor.download = effect.name;
      anchor.rel = 'noopener';
      document.body.append(anchor);
      anchor.click();
      anchor.remove();
      break;
    }
    case 'modal':
      on.openModal(effect.component, effect.data);
      break;
    case 'emit':
      window.dispatchEvent(new CustomEvent(`lathe:${effect.event}`, { detail: effect.data }));
      break;
    case 'deleted':
      on.refresh();
      break;
    case undefined:
      break;
  }
}
