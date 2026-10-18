/** A card without its value, as Widget::descriptor() sends it. Each type adds its own options. */
export interface WidgetDescriptor {
  name: string;
  uriKey: string;
  component: string;
  width: string;
  columns: number;
  height: 'fixed' | 'double' | 'dynamic';
  help: string | null;
  helpWidth: number | string;
  refreshEvery: number;
  refreshWhenActionsRun: boolean;
  refreshWhenFiltersChange: boolean;
  onlyOnDetail: boolean;
  requestsValue: boolean;
  ranges: { value: number | string; label: string }[];
  [option: string]: unknown;
}

/** A value response and a deferred value prop: {name, uriKey, component, value}. */
export interface WidgetValue {
  name: string;
  uriKey: string;
  component: string;
  value: unknown;
}

/** What every renderer gets: the card and the value field of its response. */
export interface WidgetRendererProps {
  widget: WidgetDescriptor;
  value: unknown;
}

/** How a number shows, shared by the value and progress results. */
export interface NumberDisplay {
  prefix: string | null;
  suffix: string | null;
  suffixInflection: boolean;
  format: string | Intl.NumberFormatOptions | null;
}

export interface ValueResult extends NumberDisplay {
  value: number | null;
  previous: number | null;
  change: number | null;
  previousLabel: string | null;
  allowZeroResult: boolean;
  copyable: boolean;
  lowerIsBetter?: boolean;
}

export interface ProgressResult extends NumberDisplay {
  value: number;
  target: number;
  percentage: number;
  avoid: boolean;
  color: 'destructive' | 'warning' | 'success';
  caption: string;
}
