import type { ActionResult } from './result';

/** An action's menu metadata, as GET /actions and the index payload send it. No field is resolved. */
export interface ActionMeta {
  uriKey: string;
  name: string;
  icon: string | null;
  description: string | null;
  group: string | null;
  destructive: boolean;
  standalone: boolean;
  sole: boolean;
  pinned: boolean;
  withoutConfirmation: boolean;
  confirmText: string | null;
  confirmButtonText: string;
  cancelButtonText: string;
  size: string;
  style: 'window' | 'fullscreen';
  asBlob: boolean;
  showOnIndex: boolean;
  showOnDetail: boolean;
  showOnTableRow: boolean;
  /** Set when the list was asked for one record (detail, table-row). */
  authorizedToRun?: boolean;
}

export interface ActionList {
  actions: ActionMeta[];
  pivotActions: { name: string; actions: ActionMeta[] }[];
  counts: Record<string, number>;
}

/** What POST /action answers. */
export interface ActionRun {
  result: ActionResult;
  permitted: number;
  skipped: number;
  failed: number;
  errors: { id: string | number | null; message: string }[];
  /** Set when the run went to the queue: the bulk run the progress toast follows. */
  bulk_run?: { id: string; total: number };
}
