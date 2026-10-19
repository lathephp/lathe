import { useTranslation } from '@lathephp/core';
import { type ReactNode, useState } from 'react';
import { Button } from '../components/ui/Button';
import { gridClass } from './layout';
import type { WidgetDescriptor } from './types';
import { type DeferredValue, WidgetCard } from './WidgetCard';

export interface WidgetGridProps {
  widgets: WidgetDescriptor[];
  /** The widgets endpoint; a card's value is at {valueUrl}/{uriKey}. */
  valueUrl: string;
  params?: Record<string, string>;
  /** On a dashboard: each card's deferred value prop. */
  deferred?: (uriKey: string) => DeferredValue;
  showRefreshButton?: boolean;
  /** On a dashboard: the header's range, which every card shows. */
  range?: string;
  /** The cards' reload functions, when the host has its own Refresh button. */
  reloads?: Set<() => void>;
  /** On a dashboard: gives every card its ⋯ menu. */
  menus?: boolean;
  /** In Customize mode: each card's edit header. */
  edit?: (widget: WidgetDescriptor) => ReactNode;
}

/** The cards of a dashboard, a Resource page or a lens, on a 12-column grid. */
export function WidgetGrid({ widgets, valueUrl, params, deferred, showRefreshButton = false, range, reloads: hostReloads, menus = false, edit }: WidgetGridProps) {
  const { __ } = useTranslation();
  // The cards' reload functions; one Set for the life of the grid.
  const [ownReloads] = useState(() => new Set<() => void>());
  const reloads = hostReloads ?? ownReloads;
  const register = (reload: () => void) => {
    reloads.add(reload);

    return () => {
      reloads.delete(reload);
    };
  };

  return (
    <div className="space-y-4">
      {showRefreshButton && (
        <div className="flex justify-end">
          <Button
            variant="outline"
            size="sm"
            onClick={() => {
              reloads.forEach((reload) => {
                reload();
              });
            }}
          >
            {__('Refresh')}
          </Button>
        </div>
      )}
      {/* Customize mode packs its cards 16 px apart. */}
      <div className={edit === undefined ? gridClass : 'grid grid-cols-12 gap-4'}>
        {widgets.map((widget) => (
          <WidgetCard
            key={widget.uriKey}
            widget={widget}
            url={`${valueUrl}/${widget.uriKey}`}
            params={params}
            deferred={deferred?.(widget.uriKey)}
            register={register}
            range={range}
            menu={menus}
            edit={edit?.(widget)}
          />
        ))}
      </div>
    </div>
  );
}
