import { useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { countText } from './IndexFooter';
import type { Count, Row } from './types';
import type { RowSelection } from './useRowSelection';

interface SelectionBannerProps {
  selection: RowSelection;
  ids: Row['id'][];
  /** Undefined while the deferred count loads, null when the Resource counts nothing. */
  count: Count | null | undefined;
  hasMorePages: boolean;
}

/** Offers every matching row once the whole page is ticked, and says when they are all selected. */
export function SelectionBanner({ selection, ids, count, hasMorePages }: SelectionBannerProps) {
  const { __ } = useTranslation();
  const total = count == null ? null : countText(count);

  if (selection.all) {
    return (
      <div role="status" className="flex flex-wrap items-center justify-center gap-2 border-b border-border bg-muted/50 px-3 py-2 text-sm">
        <span>{total === null ? __('All matching rows selected') : __('All matching rows selected (:count)', { count: total })}</span>
        <Button variant="ghost" size="sm" onClick={selection.clear}>
          {__('Clear')}
        </Button>
      </div>
    );
  }

  const pageTicked = ids.length > 0 && ids.every((id) => selection.isSelected(id));
  const more = hasMorePages || (count != null && count.value > ids.length);

  if (!pageTicked || !more) {
    return null;
  }

  return (
    <div role="status" className="flex flex-wrap items-center justify-center gap-2 border-b border-border bg-muted/50 px-3 py-2 text-sm">
      <span>{__('All :count rows on this page are selected.', { count: ids.length })}</span>
      <Button variant="ghost" size="sm" onClick={selection.selectAllMatching}>
        {total === null ? __('Select all matching rows') : __('Select all matching rows (:count)', { count: total })}
      </Button>
    </div>
  );
}
