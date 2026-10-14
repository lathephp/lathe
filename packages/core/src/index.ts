import { setRouter } from './navigation';
import { sharedProps } from './props';
import {
  actionModalComponent,
  boot,
  fieldComponent,
  filterComponent,
  pageComponent,
  pluginPage,
  renderHooks,
  resourcePluginComponent,
  widgetComponent,
} from './registry';
import { registerComponents } from './components';
import { navigating } from './data';
import { RenderHook } from './render-hooks';
import { Slot } from './slots';
import { dismissToast, toasts } from './toast';
import { setPasswordConfirmer } from './http';
import { loadModules } from './loader';
import { shortcutPauseCount } from './shortcuts';
import { setTranslator, translatorFor } from './translation';

/** The plugin API version this package provides. */
export const apiVersion = '1.0.0';

export { actionModal, booting, field, filter, page, plugin, renderHook, resourcePlugin, widget } from './registry';
export type {
  ActionModalProps,
  ComponentOrLazy,
  FieldProps,
  FieldRenderers,
  FilterProps,
  PageProps,
  PluginPageProps,
  RenderHookName,
  RenderHookProps,
  ResourcePluginProps,
  WidgetProps,
} from './registry';
export { registry } from './slots';
export type { SlotProps } from './slots';
export { config, router, url, visit } from './navigation';
export type { PanelRouter } from './navigation';
export { ApiError, csrfHeaders, request } from './http';
export type { RequestOptions } from './http';
export { toast } from './toast';
export type { ToastAction, ToastLink, ToastMessage, ToastOptions, ToastType } from './toast';
export { emit, off, on, once } from './events';
export { addShortcut, disableShortcut, enableShortcut, pauseShortcuts, resumeShortcuts, shortcutsEnabled, useShortcut, usePauseShortcuts } from './shortcuts';
export { QueryClientProvider, queryClient, queryKeys, useQuery, useQueryClient } from './query';
export { formatCurrency, formatDate, formatDateTime, formatNumber } from './format';
export { useLathe,useTranslation } from './hooks';
export { useTheme } from './theme';
export type { Appearance } from './theme';
export {
  useDebounce,
  useExecuteAction,
  useInlineField,
  useNavigationProgress,
  useResourceActions,
  useResourceDetail,
  useResourceIndex,
  useWidgetList,
  useWidgetValue,
} from './data';
export {
  ActionDropdown,
  AppLayout,
  DetailFieldPanel,
  DetailHeader,
  Excerpt,
  FilterMenu,
  ResourceTable,
  WidgetCard,
  WidgetGrid,
} from './components';
export type {
  ActionData,
  ActionModalComponentProps,
  BadgeData,
  BadgeType,
  CustomPageProps,
  CustomWidgetComponentProps,
  DetailFieldProps,
  FieldData,
  FilterComponentProps,
  FilterData,
  FormFieldProps,
  GlobalSearchResult,
  IndexFieldProps,
  LayoutBlock,
  NotificationData,
  PaginatedResponse,
  PluginMeta,
  RenderHookContext,
  RenderHookOptions,
  ResourceDetailResponse,
  ResourceIndexResponse,
  ResourceMeta,
  ResourcePluginLayout,
  ResourceRow,
  SectionLayout,
  TabGroupLayout,
  TabLayout,
  WidgetComponentProps,
  WidgetData,
} from './types';
export type { LatheContext } from './hooks';
export type { SharedData } from './props';
export type { SafeHtml } from './safe-html';
export type { Translator } from './translation';

/**
 * What the panel itself uses to run the API. Not for plugins.
 *
 * @internal
 */
export const __panel = {
  RenderHook,
  actionModalComponent,
  boot,
  dismissToast,
  fieldComponent,
  filterComponent,
  loadModules,
  navigating,
  pageComponent,
  pluginPage,
  registerComponents,
  renderHooks,
  resourcePluginComponent,
  setRouter,
  setPasswordConfirmer,
  setTranslator,
  sharedProps,
  shortcutPauseCount,
  Slot,
  toasts,
  translatorFor,
  widgetComponent,
};
