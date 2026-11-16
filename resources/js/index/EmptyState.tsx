import { Link } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { Inbox, SearchX } from 'lucide-react';
import { Button, buttonClass } from '../components/ui/Button';

interface EmptyStateProps {
  label: string;
  /** q or a filter is set, so the rows may exist and are only hidden. */
  filtered: boolean;
  /** The create page, or null when the user may not create. */
  createHref: string | null;
  onClear: () => void;
}

// The kit Empty's class names and slots, written by hand: this paints on first load, which the kit stays out of.
export function EmptyState({ label, filtered, createHref, onClear }: EmptyStateProps) {
  const { __ } = useTranslation();

  const create =
    createHref === null ? null : (
      <Link href={createHref} className={buttonClass('default', 'sm')}>
        {__('Create')}
      </Link>
    );

  return (
    <div data-slot="empty" className="cn-empty flex w-full min-w-0 flex-1 flex-col items-center justify-center text-center text-balance">
      <div data-slot="empty-header" className="cn-empty-header flex max-w-sm flex-col items-center">
        <div data-slot="empty-icon" data-variant="icon" className="cn-empty-media cn-empty-media-icon flex shrink-0 items-center justify-center">
          {filtered ? <SearchX aria-hidden="true" className="size-4" /> : <Inbox aria-hidden="true" className="size-4" />}
        </div>
        <div data-slot="empty-title" className="cn-empty-title cn-font-heading">
          {filtered ? __('No results match your filters') : __('No :resource yet', { resource: label })}
        </div>
      </div>
      {filtered ? (
        <div data-slot="empty-content" className="cn-empty-content flex w-full min-w-0 max-w-sm flex-col items-center text-balance">
          <div className="flex flex-wrap justify-center gap-2">
            <Button variant="outline" size="sm" onClick={onClear}>
              {__('Clear filters')}
            </Button>
            {create}
          </div>
        </div>
      ) : (
        create && (
          <div data-slot="empty-content" className="cn-empty-content flex w-full min-w-0 max-w-sm flex-col items-center text-balance">
            {create}
          </div>
        )
      )}
    </div>
  );
}
