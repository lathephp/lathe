import { useTranslation } from '@lathephp/core';
import { Card } from '@lathephp/ui';
import { ChevronDown } from 'lucide-react';
import { lazy, Suspense, useState, type ReactNode } from 'react';
import { SafeHtml } from '../components/SafeHtml';
import type { DetailFieldData, LayoutBlock, PanelBlock, RelationFieldSchema, TabData, TabGroupBlock } from '../fields/types';
import { cn } from '../lib/cn';
import { SingularRelationPanel } from './SingularRelationPanel';

// Panels draw only on detail pages, so a form never loads the table code they share with the index.
const RelationPanel = lazy(() => import('../relations/RelationPanel').then((module) => ({ default: module.RelationPanel })));

const columnClass: Record<number, string> = {
  1: 'md:grid-cols-1',
  2: 'md:grid-cols-2',
  3: 'md:grid-cols-3',
  4: 'md:grid-cols-4',
  5: 'md:grid-cols-5',
  6: 'md:grid-cols-6',
};

// The relation kinds drawn as a RelationPanel; a has-one block is drawn elsewhere.
const panelKinds = ['hasMany', 'morphMany', 'belongsToMany', 'morphToMany', 'morphedByMany', 'hasManyThrough', 'hasOneThrough'];

const badgeClass: Record<string, string> = {
  info: 'bg-sky-100 text-sky-800',
  success: 'bg-emerald-100 text-emerald-800',
  warning: 'bg-amber-100 text-amber-800',
  danger: 'bg-red-100 text-red-800',
};

export interface RecordLayoutProps {
  blocks: LayoutBlock[];
  /** Draws one field of a panel, by key; null when the key has no field here. */
  renderField: (key: string) => ReactNode;
  /** The field keys a failed submit marked; their tabs get a dot and the first one opens. */
  errorKeys?: ReadonlySet<string>;
  /** The Resource's borderless(): no divider lines anywhere. */
  borderless?: boolean;
  /** Detail pages cut a panel at its limit(); forms show every field. */
  detail?: boolean;
  /** The record of a detail page, whose relation panels list its related rows. */
  parent?: { uriKey: string; id: string | number };
  /** The detail page's fields by key, which the hasOne blocks draw from. */
  detailFields?: ReadonlyMap<string, DetailFieldData>;
}

/** The panels, grids and tab groups of a detail page or a form. Below 768 px every grid is one column. */
export function RecordLayout(props: RecordLayoutProps) {
  return (
    <div className="space-y-6">
      {props.blocks.map((block, index) => (
        <Block key={blockKey(block, index)} block={block} {...props} />
      ))}
    </div>
  );
}

function blockKey(block: LayoutBlock, index: number): string {
  if (block.type === 'grid') {
    return `grid-${String(index)}`;
  }

  return block.type === 'panel' || block.type === 'tab-group' ? block.attribute : `${block.type}-${block.key}`;
}

/** Every field key inside a block, through its grids and tabs. */
function fieldKeys(block: LayoutBlock): string[] {
  if (block.type === 'panel') {
    return block.fields;
  }

  if (block.type === 'grid') {
    return block.items.flatMap(fieldKeys);
  }

  return block.type === 'tab-group' ? block.tabs.flatMap((tab) => tab.items.flatMap(fieldKeys)) : [];
}

function hasError(tab: TabData, errorKeys: ReadonlySet<string> | undefined): boolean {
  return errorKeys !== undefined && tab.items.flatMap(fieldKeys).some((key) => errorKeys.has(key));
}

function Block({ block, ...props }: RecordLayoutProps & { block: LayoutBlock }) {
  if (block.type === 'panel') {
    return <Panel panel={block} {...props} />;
  }

  if (block.type === 'grid') {
    return (
      <div className={cn('grid grid-cols-1 gap-6', columnClass[block.columns])}>
        {block.items.map((item, index) => (
          <Block key={blockKey(item, index)} block={item} {...props} />
        ))}
      </div>
    );
  }

  if (block.type === 'tab-group') {
    return <TabGroup group={block} {...props} />;
  }

  const field = block.field as RelationFieldSchema;

  if (block.type === 'relation-field') {
    return props.parent !== undefined && panelKinds.includes(field.kind) ? (
      <Suspense fallback={null}>
        <RelationPanel parent={props.parent} field={field} />
      </Suspense>
    ) : null;
  }

  const detailField = props.detailFields?.get(block.key);

  return detailField === undefined ? null : <SingularRelationPanel field={detailField} />;
}

function Panel({ panel, renderField, borderless, detail }: RecordLayoutProps & { panel: PanelBlock }) {
  const { __ } = useTranslation();
  const [open, setOpen] = useState(!panel.collapsed);
  const [all, setAll] = useState(false);
  const limited = detail === true && panel.limit !== null && !all && panel.fields.length > panel.limit;
  const keys = limited ? panel.fields.slice(0, panel.limit ?? undefined) : panel.fields;
  const bodyId = `panel-${panel.attribute}`;
  const heading = panel.hideHeading ? null : panel.collapsible ? (
    <button
      type="button"
      aria-expanded={open}
      aria-controls={bodyId}
      className="flex items-center gap-1 text-base font-semibold"
      onClick={() => {
        setOpen(!open);
      }}
    >
      <ChevronDown aria-hidden="true" className={cn('size-4 transition-transform', !open && '-rotate-90')} />
      {panel.name}
    </button>
  ) : (
    <h2 className="text-base font-semibold">{panel.name}</h2>
  );

  return (
    <section aria-label={panel.name} className="space-y-2">
      {panel.headingInCard ? null : heading}
      {panel.help === null ? null : <SafeHtml html={panel.help} className="text-sm text-muted-foreground" />}
      <Card id={bodyId} hidden={!open} className={cn('divide-y divide-border', (borderless === true || panel.borderless) && 'divide-y-0')}>
        {panel.headingInCard ? <div className="px-4 pt-3">{heading}</div> : null}
        {keys.map((key) => (
          <div key={key}>{renderField(key)}</div>
        ))}
        {limited ? (
          <div className="px-4 py-3">
            <button
              type="button"
              className="text-sm font-medium text-primary hover:underline"
              onClick={() => {
                setAll(true);
              }}
            >
              {__('Show all')}
            </button>
          </div>
        ) : null}
      </Card>
    </section>
  );
}

/** The tab the URL names, else activeTab. */
function initialTab(group: TabGroupBlock): number {
  const named = typeof window === 'undefined' ? null : new URLSearchParams(window.location.search).get(group.queryKey);
  const index = group.tabs.findIndex((tab) => tab.attribute === named);

  return index >= 0 ? index : Math.min(Math.max(group.activeTab, 0), group.tabs.length - 1);
}

function TabGroup({ group, ...props }: RecordLayoutProps & { group: TabGroupBlock }) {
  const { __ } = useTranslation();
  const [active, setActive] = useState(() => initialTab(group));
  // A detail page mounts a tab when it first opens, so its relation panels load then (PERF-7).
  const [opened, setOpened] = useState<ReadonlySet<number>>(() => new Set([active]));
  const { errorKeys } = props;

  const [seenErrors, setSeenErrors] = useState(errorKeys);

  // A failed submit opens the first tab it marked.
  if (errorKeys !== seenErrors) {
    setSeenErrors(errorKeys);
    const failed = group.tabs.findIndex((tab) => hasError(tab, errorKeys));

    if (failed >= 0) {
      setActive(failed);
    }
  }

  function select(index: number) {
    setActive(index);
    setOpened(new Set(opened).add(index));
    const url = new URL(window.location.href);
    url.searchParams.set(group.queryKey, group.tabs[index]?.attribute ?? '');
    window.history.replaceState(window.history.state, '', url);
  }

  return (
    <section aria-label={group.label ?? undefined} className={cn('space-y-3', group.vertical && 'md:flex md:gap-6 md:space-y-0')}>
      <div role="tablist" aria-orientation={group.vertical ? 'vertical' : 'horizontal'} className={cn('flex gap-1', group.vertical ? 'md:flex-col' : 'border-b border-border')}>
        {group.tabs.map((tab, index) => (
          <button
            key={tab.attribute}
            id={`tab-${group.attribute}-${tab.attribute}`}
            type="button"
            role="tab"
            aria-selected={index === active}
            aria-controls={`tabpanel-${group.attribute}-${tab.attribute}`}
            className={cn(
              '-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-sm',
              index === active ? 'border-primary font-medium' : 'border-transparent text-muted-foreground',
            )}
            onClick={() => {
              select(index);
            }}
          >
            {tab.label}
            {tab.badge === null ? null : <span className={cn('rounded-full px-2 text-xs', badgeClass[tab.badgeType ?? 'info'])}>{tab.badge}</span>}
            {hasError(tab, errorKeys) ? (
              <span role="img" aria-label={__('Has errors')} className="size-2 rounded-full bg-destructive" />
            ) : null}
          </button>
        ))}
      </div>
      {group.tabs.map((tab, index) => (
        <div
          key={tab.attribute}
          id={`tabpanel-${group.attribute}-${tab.attribute}`}
          role="tabpanel"
          aria-labelledby={`tab-${group.attribute}-${tab.attribute}`}
          hidden={index !== active}
          className={cn(group.vertical && 'md:flex-1')}
        >
          {props.detail === true && !opened.has(index) ? null : (
            <RecordLayout {...props} blocks={tab.items} borderless={props.borderless === true || tab.borderless} />
          )}
        </div>
      ))}
    </section>
  );
}
