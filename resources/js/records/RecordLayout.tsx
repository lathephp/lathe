import { useTranslation } from '@lathephp/core';
import { ChevronDown } from 'lucide-react';
import { Fragment, lazy, Suspense, useState, type ReactNode } from 'react';
import { Card } from '../components/ui/Card';
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
  info: 'bg-info-subtle text-info-subtle-foreground',
  success: 'bg-success-subtle text-success-subtle-foreground',
  warning: 'bg-warning-subtle text-warning-subtle-foreground',
  danger: 'bg-destructive-subtle text-destructive-subtle-foreground',
};

export interface RecordLayoutProps {
  blocks: LayoutBlock[];
  /** Draws one field of a panel, by key; null when the key has no field here. A form learns which field opens its panel. */
  renderField: (key: string, first?: boolean) => ReactNode;
  /** The field keys that hold an error now; their tabs get a dot. */
  errorKeys?: ReadonlySet<string>;
  /** Counts the failed saves: each new one opens the first tab holding an error, once. */
  failures?: number;
  /** The Resource's borderless(): no divider lines anywhere. */
  borderless?: boolean;
  /** Detail pages cut a panel at its limit(); forms show every field. */
  detail?: boolean;
  /** A form's sections without their cards, in one column, as in a sheet. */
  bare?: boolean;
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

function Panel({ panel, renderField, borderless, detail, bare }: RecordLayoutProps & { panel: PanelBlock }) {
  const { __ } = useTranslation();
  const [open, setOpen] = useState(!panel.collapsed);
  const [all, setAll] = useState(false);
  const limited = detail === true && panel.limit !== null && !all && panel.fields.length > panel.limit;
  const keys = limited ? panel.fields.slice(0, panel.limit ?? undefined) : panel.fields;
  const bodyId = `panel-${panel.attribute}`;
  const fieldsId = `${bodyId}-fields`;
  const Frame = bare === true ? 'div' : Card;
  const heading = panel.hideHeading ? null : panel.collapsible ? (
    <button
      type="button"
      aria-expanded={open}
      aria-controls={fieldsId}
      className="flex items-center gap-1 text-base font-semibold"
      onClick={() => {
        setOpen(!open);
      }}
    >
      <ChevronDown aria-hidden="true" className={cn('size-4 transition-transform', !open && '-rotate-90 rtl:rotate-90')} />
      {panel.name}
    </button>
  ) : (
    <h2 className="text-base font-semibold">{panel.name}</h2>
  );

  // The heading sits in the card's header row; a collapsed panel keeps that row and hides its fields.
  return (
    <section aria-label={panel.name} className="space-y-2">
      {panel.help === null ? null : <SafeHtml html={panel.help} className="text-sm text-muted-foreground" />}
      <Frame
        id={bodyId}
        className={
          // A sheet lays the fields straight on its surface, and so does a phone, as the phone form board does.
          bare === true
            ? '[&>*]:p-0 [&>header]:pb-3'
            : cn(
                'divide-y divide-border',
                (borderless === true || panel.borderless) && 'divide-y-0',
                detail !== true && 'max-md:divide-y-0 max-md:rounded-none max-md:border-0 max-md:bg-transparent max-md:shadow-none max-md:[&>*]:px-0 max-md:[&>header]:pt-0 max-md:[&>header]:pb-3',
              )
        }
      >
        {heading === null ? null : <header className="flex items-center justify-between px-5 py-4">{heading}</header>}
        {detail === true ? (
          <div id={fieldsId} hidden={!open} className={cn('divide-y divide-border', (borderless === true || panel.borderless) && 'divide-y-0')}>
            {keys.map((key) => (
              <div key={key}>{renderField(key)}</div>
            ))}
          </div>
        ) : (
          // A form lays its fields in one column below 1024 px and two from there; each row is a grid item.
          <div id={fieldsId} hidden={!open} className={bare === true ? 'grid gap-[18px] [&>*]:col-auto' : 'grid gap-x-5 gap-y-4 p-5 max-md:py-0 lg:grid-cols-2'}>
            {keys.map((key, index) => (
              <Fragment key={key}>{renderField(key, index === 0)}</Fragment>
            ))}
          </div>
        )}
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
      </Frame>
    </section>
  );
}

/** The keys of the relation panels inside a block, through its grids. */
function relationKeys(block: LayoutBlock): string[] {
  if (block.type === 'grid') {
    return block.items.flatMap(relationKeys);
  }

  return block.type === 'relation-field' ? [block.key] : [];
}

/**
 * The tab the URL names, else the tab that holds the relation panel it names, else activeTab.
 * A save under a via comes back with the relationship as the tab value.
 */
function initialTab(group: TabGroupBlock): number {
  const named = typeof window === 'undefined' ? null : new URLSearchParams(window.location.search).get(group.queryKey);
  const byKey = group.tabs.findIndex((tab) => tab.attribute === named);
  const index = byKey >= 0 ? byKey : group.tabs.findIndex((tab) => tab.items.flatMap(relationKeys).includes(named ?? ''));

  return index >= 0 ? index : Math.min(Math.max(group.activeTab, 0), group.tabs.length - 1);
}

function TabGroup({ group, ...props }: RecordLayoutProps & { group: TabGroupBlock }) {
  const { __ } = useTranslation();
  const [active, setActive] = useState(() => initialTab(group));
  // A detail page mounts a tab when it first opens, so its relation panels load then (PERF-7).
  const [opened, setOpened] = useState<ReadonlySet<number>>(() => new Set([active]));
  const { errorKeys, failures } = props;

  const [seenFailures, setSeenFailures] = useState(failures);

  // A failed save opens the first tab it marked; fixing a field later leaves the tab alone.
  if (failures !== seenFailures) {
    setSeenFailures(failures);
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
