import { Link } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { Inbox, SearchX } from 'lucide-react';
import { Button } from '../components/ui/Button';

interface EmptyStateProps {
  label: string;
  /** q or a filter is set, so the rows may exist and are only hidden. */
  filtered: boolean;
  /** The create page, or null when the user may not create. */
  createHref: string | null;
  onClear: () => void;
}

// The icon in a 40 px round badge, the title, then the next steps; the looks live in app.css.
export function EmptyState({ label, filtered, createHref, onClear }: EmptyStateProps) {
  const { __ } = useTranslation();

  const create =
    createHref === null ? null : (
      <Link href={createHref} className="lathe-button lathe-button-default lathe-button-size-sm">
        {__('Create')}
      </Link>
    );

  if (!filtered) {
    return (
      <div className="lathe-empty">
        <span data-tone="primary">
          <Inbox aria-hidden="true" className="size-[18px]" />
        </span>
        <p className="lathe-empty-title">{__('No :resource yet', { resource: label.toLowerCase() })}</p>
        {create}
      </div>
    );
  }

  return (
    <div className="lathe-empty">
      <span>
        <SearchX aria-hidden="true" className="size-[18px]" />
      </span>
      <p className="lathe-empty-title">{__('No results match your filters')}</p>
      <div className="flex flex-wrap justify-center gap-2">
        <Button variant="outline" size="sm" onClick={onClear}>
          {__('Clear filters')}
        </Button>
        {create}
      </div>
    </div>
  );
}
