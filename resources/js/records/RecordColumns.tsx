import { useTranslation } from '@lathephp/core';
import { lazy, Suspense, useId, useState, type ReactNode } from 'react';
import { Card } from '../components/ui/Card';
import { DetailField } from '../fields/registry';
import type { DetailFieldData, DetailRecord, LayoutBlock, RelationFieldSchema } from '../fields/types';
import { cn } from '../lib/cn';
import { RecordLayout } from './RecordLayout';

// The panels load their table code only when a tab opens, as they did inside RecordLayout.
const RelationPanel = lazy(() => import('../relations/RelationPanel').then((module) => ({ default: module.RelationPanel })));

// The relation kinds drawn as a RelationPanel, the same list RecordLayout keeps.
const panelKinds = ['hasMany', 'morphMany', 'belongsToMany', 'morphToMany', 'morphedByMany', 'hasManyThrough', 'hasOneThrough'];

// The field components that point at one related record, each drawn as a card of its own.
const singularComponents = ['belongs-to-field', 'morph-to-field'];

// The query key a save under a via comes back with (Form.tsx), naming the relationship.
const tabQueryKey = 'tab';

type Parent = { uriKey: string; id: string | number };

/** One card of the right column: a belongsTo or morphTo field, or the block of a hasOne field. */
interface SingularCard {
  field: DetailFieldData;
  block: LayoutBlock | null;
}

interface Columns {
  tabs: RelationFieldSchema[];
  wide: DetailFieldData[];
  details: DetailFieldData[];
  singular: SingularCard[];
}

/** The keys of the relation blocks anywhere in the layout, so no panel lists them in Details. */
function relationKeys(blocks: LayoutBlock[]): string[] {
  return blocks.flatMap((block) => {
    if (block.type === 'grid') {
      return relationKeys(block.items);
    }

    if (block.type === 'tab-group') {
      return block.tabs.flatMap((tab) => relationKeys(tab.items));
    }

    return block.type === 'panel' ? [] : [block.key];
  });
}

/**
 * Sorts the layout into the two columns, in layout order. Sections, grids and field tab groups
 * only order the fields here; the forms keep them.
 */
function columns(record: DetailRecord): Columns {
  const byKey = new Map(record.fields.map((field) => [field.key, field]));
  const left = new Set([record.header?.badge, record.header?.meta, record.titleKey, ...relationKeys(record.layout)]);
  const result: Columns = { tabs: [], wide: [], details: [], singular: [] };

  function walk(blocks: LayoutBlock[]) {
    for (const block of blocks) {
      if (block.type === 'grid') {
        walk(block.items);
      } else if (block.type === 'tab-group') {
        block.tabs.forEach((tab) => {
          walk(tab.items);
        });
      } else if (block.type === 'panel') {
        for (const key of block.fields) {
          const field = byKey.get(key);

          if (field === undefined || left.has(key)) {
            continue;
          }

          if (singularComponents.includes(field.component)) {
            result.singular.push({ field, block: null });
          } else if (field.stacked) {
            result.wide.push(field);
          } else {
            result.details.push(field);
          }
        }
      } else if (block.type === 'relation-field') {
        const field = block.field as RelationFieldSchema;

        if (panelKinds.includes(field.kind)) {
          result.tabs.push(field);
        }
      } else {
        const field = byKey.get(block.key);

        if (field !== undefined) {
          result.singular.push({ field, block });
        }
      }
    }
  }

  walk(record.layout);

  return result;
}

/**
 * A detail page's body: the to-many relations as tabs and the wide fields in the main column,
 * then the Details card and one card per related record in the right one. Below 1024 px the
 * right column follows the main one.
 */
export function RecordColumns({ record, parent }: { record: DetailRecord; parent: Parent }) {
  const { __ } = useTranslation();
  const { tabs, wide, details, singular } = columns(record);
  const detailsCard =
    details.length === 0 ? null : (
      <FieldCard label={__('Details')}>
        <dl className="grid grid-cols-[minmax(0,1fr)_minmax(0,2fr)] gap-x-4 gap-y-3 px-4 py-3 text-sm">
          {details.map((field) => (
            <div key={field.key} className="contents">
              <dt className="text-muted-foreground">{field.label}</dt>
              <dd className={cn('min-w-0 break-words', field.textAlign === 'right' && 'text-end', field.textAlign === 'center' && 'text-center')}>
                <DetailField field={field} />
              </dd>
            </div>
          ))}
        </dl>
      </FieldCard>
    );
  // With nothing else to show, the Details card takes the main column.
  const mainHolds = tabs.length > 0 || wide.length > 0;

  return (
    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <div data-detail-column="main" className="min-w-0 space-y-6 lg:col-span-2">
        {tabs.length > 0 ? <RelationTabs parent={parent} tabs={tabs} counts={record.counts ?? {}} /> : null}
        {wide.map((field) => (
          <FieldCard key={field.key} label={field.label}>
            <div className="min-w-0 break-words px-4 py-3 text-sm">
              <DetailField field={field} />
            </div>
          </FieldCard>
        ))}
        {mainHolds ? null : detailsCard}
      </div>
      <div data-detail-column="side" className="min-w-0 space-y-6">
        {mainHolds ? detailsCard : null}
        {singular.map((card) =>
          card.block !== null ? (
            // RecordLayout draws a hasOne block as SingularRelationPanel, which then stays in the chunk forms share.
            <RecordLayout key={card.field.key} blocks={[card.block]} detailFields={new Map([[card.field.key, card.field]])} renderField={() => null} />
          ) : (
            <FieldCard key={card.field.key} label={card.field.label}>
              <div className="min-w-0 break-words px-4 py-3 text-sm">
                <DetailField field={card.field} />
              </div>
            </FieldCard>
          ),
        )}
        {/* The cards that follow the related records go here, at the end of the right column. */}
      </div>
    </div>
  );
}

function FieldCard({ label, children }: { label: string; children: ReactNode }) {
  return (
    <section aria-label={label} className="space-y-2">
      <h2 className="text-base font-semibold">{label}</h2>
      <Card>{children}</Card>
    </section>
  );
}

/** The tab the URL names by relation, else the first. A save under a via comes back that way. */
function initialTab(tabs: RelationFieldSchema[]): number {
  const named = typeof window === 'undefined' ? null : new URLSearchParams(window.location.search).get(tabQueryKey);
  const index = tabs.findIndex((field) => field.key === named || field.relationship === named);

  return Math.max(index, 0);
}

function RelationTabs({ parent, tabs, counts }: { parent: Parent; tabs: RelationFieldSchema[]; counts: Record<string, number> }) {
  const id = useId();
  const [active, setActive] = useState(() => initialTab(tabs));
  // A tab mounts when it first opens, so its panel loads then.
  const [opened, setOpened] = useState<ReadonlySet<number>>(() => new Set([active]));

  function select(index: number) {
    setActive(index);
    setOpened(new Set(opened).add(index));
    const url = new URL(window.location.href);
    url.searchParams.set(tabQueryKey, tabs[index]?.key ?? '');
    window.history.replaceState(window.history.state, '', url);
  }

  return (
    <div className="space-y-3">
      <div role="tablist" aria-orientation="horizontal" className="flex gap-1 border-b border-border">
        {tabs.map((field, index) => {
          const count = field.withCount ? counts[field.key] : undefined;

          return (
            <button
              key={field.key}
              id={`${id}-tab-${field.key}`}
              type="button"
              role="tab"
              aria-selected={index === active}
              aria-controls={`${id}-panel-${field.key}`}
              className={cn(
                '-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-sm',
                index === active ? 'border-primary font-medium' : 'border-transparent text-muted-foreground',
              )}
              onClick={() => {
                select(index);
              }}
            >
              {field.label}
              {count === undefined ? null : (
                <>
                  {' '}
                  <span className="rounded-full bg-muted px-2 text-xs font-medium text-muted-foreground">{String(count)}</span>
                </>
              )}
            </button>
          );
        })}
      </div>
      {tabs.map((field, index) => (
        <div key={field.key} id={`${id}-panel-${field.key}`} role="tabpanel" aria-labelledby={`${id}-tab-${field.key}`} hidden={index !== active}>
          {opened.has(index) ? (
            <Suspense fallback={null}>
              {/* A tab shows its panel open; collapsed() folds it only outside a tab. */}
              <RelationPanel parent={parent} field={{ ...field, collapsed: false }} />
            </Suspense>
          ) : null}
        </div>
      ))}
    </div>
  );
}
