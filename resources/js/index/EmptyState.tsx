import { Link } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { Button } from '../components/ui/Button';

interface EmptyStateProps {
  label: string;
  /** q or a filter is set, so the rows may exist and are only hidden. */
  filtered: boolean;
  /** The create page, or null when the user may not create. */
  createHref: string | null;
  onClear: () => void;
}

export function EmptyState({ label, filtered, createHref, onClear }: EmptyStateProps) {
  const { __ } = useTranslation();

  const create =
    createHref === null ? null : (
      <Link
        href={createHref}
        className="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground outline-none hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring"
      >
        {__('Create')}
      </Link>
    );

  if (!filtered) {
    return (
      <div className="space-y-4 p-12 text-center text-sm text-muted-foreground">
        <p>{__('No :resource yet', { resource: label.toLowerCase() })}</p>
        {create}
      </div>
    );
  }

  return (
    <div className="space-y-4 p-12 text-center text-sm text-muted-foreground">
      <p>{__('No results match your filters')}</p>
      <div className="flex flex-wrap justify-center gap-2">
        <Button variant="outline" onClick={onClear}>
          {__('Clear filters')}
        </Button>
        {create}
      </div>
    </div>
  );
}
