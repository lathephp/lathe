import { Card } from '@lathephp/ui';
import { useState, type ReactNode } from 'react';
import type { LayoutBlock, PanelBlock, TabGroupBlock } from '../fields/types';
import { cn } from '../lib/cn';

const columnClass: Record<number, string> = {
  1: 'md:grid-cols-1',
  2: 'md:grid-cols-2',
  3: 'md:grid-cols-3',
  4: 'md:grid-cols-4',
  5: 'md:grid-cols-5',
  6: 'md:grid-cols-6',
};

export interface RecordLayoutProps {
  blocks: LayoutBlock[];
  /** Draws one field of a panel, by key; null when the key has no field here. */
  renderField: (key: string) => ReactNode;
}

/** The panels, grids and tab groups of a detail page or a form. Below 768 px every grid is one column. */
export function RecordLayout({ blocks, renderField }: RecordLayoutProps) {
  return (
    <div className="space-y-6">
      {blocks.map((block, index) => (
        <Block key={blockKey(block, index)} block={block} renderField={renderField} />
      ))}
    </div>
  );
}

function blockKey(block: LayoutBlock, index: number): string {
  return block.type === 'grid' ? `grid-${String(index)}` : block.attribute;
}

function Block({ block, renderField }: { block: LayoutBlock; renderField: RecordLayoutProps['renderField'] }) {
  if (block.type === 'panel') {
    return <Panel panel={block} renderField={renderField} />;
  }

  if (block.type === 'grid') {
    return (
      <div className={cn('grid grid-cols-1 gap-6', columnClass[block.columns])}>
        {block.items.map((item, index) => (
          <Block key={blockKey(item, index)} block={item} renderField={renderField} />
        ))}
      </div>
    );
  }

  return <TabGroup group={block} renderField={renderField} />;
}

function Panel({ panel, renderField }: { panel: PanelBlock; renderField: RecordLayoutProps['renderField'] }) {
  return (
    <section aria-label={panel.name} className="space-y-2">
      {panel.hideHeading ? null : <h2 className="text-base font-semibold">{panel.name}</h2>}
      <Card className={cn('divide-y divide-border', panel.borderless && 'divide-y-0')}>
        {panel.fields.map((key) => (
          <div key={key}>{renderField(key)}</div>
        ))}
      </Card>
    </section>
  );
}

function TabGroup({ group, renderField }: { group: TabGroupBlock; renderField: RecordLayoutProps['renderField'] }) {
  const [active, setActive] = useState(Math.min(group.activeTab, group.tabs.length - 1));

  return (
    <section aria-label={group.label ?? undefined} className="space-y-3">
      <div role="tablist" className="flex gap-1 border-b border-border">
        {group.tabs.map((tab, index) => (
          <button
            key={tab.attribute}
            type="button"
            role="tab"
            aria-selected={index === active}
            className={cn(
              '-mb-px border-b-2 px-3 py-2 text-sm',
              index === active ? 'border-primary font-medium' : 'border-transparent text-muted-foreground',
            )}
            onClick={() => {
              setActive(index);
            }}
          >
            {tab.label}
          </button>
        ))}
      </div>
      {group.tabs.map((tab, index) => (
        <div key={tab.attribute} role="tabpanel" hidden={index !== active}>
          <RecordLayout blocks={tab.items} renderField={renderField} />
        </div>
      ))}
    </section>
  );
}
