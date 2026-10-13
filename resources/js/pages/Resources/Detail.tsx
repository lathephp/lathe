import { Head, Link, router, usePage } from '@inertiajs/react';
import { shortcutsEnabled, useShortcut, useTranslation } from '@lathephp/core';
import { DetailField } from '../../fields/registry';
import type { DetailFieldData, DetailProps } from '../../fields/types';
import { cn } from '../../lib/cn';
import { Breadcrumbs } from '../../records/Breadcrumbs';
import { RecordLayout } from '../../records/RecordLayout';
import type { SharedProps } from '../../types';

/** One record: its fields in the Resource's panels, grids and tabs. */
export default function Detail({ resource, record, breadcrumbs }: DetailProps) {
  const { id, title, subtitle, fields, layout, abilities } = record;
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
  const byKey = new Map(fields.map((field) => [field.key, field]));
  const recordPath = `${basePath}/resources/${resource.uriKey}/${String(id)}`;

  useShortcut(
    'e',
    () => {
      router.visit(`${recordPath}/edit`);
    },
    abilities.update,
  );

  return (
    <div className="space-y-6">
      <Head title={title} />
      <Breadcrumbs items={breadcrumbs} />
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0">
          <h1 className="truncate text-xl font-semibold">{title}</h1>
          {subtitle === '' ? null : <p className="text-sm text-muted-foreground">{subtitle}</p>}
        </div>
        {abilities.update ? (
          <Link
            href={`${recordPath}/edit`}
            aria-keyshortcuts="e"
            className="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground"
          >
            {__('Edit')}
            {shortcutsEnabled() && (
              <kbd aria-hidden="true" className="text-xs opacity-80 max-md:hidden">{'e'.toUpperCase()}</kbd>
            )}
          </Link>
        ) : null}
      </header>
      <RecordLayout
        detail
        blocks={layout}
        renderField={(key) => {
          const field = byKey.get(key);

          return field === undefined ? null : <DetailRow field={field} />;
        }}
      />
    </div>
  );
}

function DetailRow({ field }: { field: DetailFieldData }) {
  return (
    <div className={cn('gap-2 px-4 py-3 text-sm', field.stacked ? 'flex flex-col' : 'grid md:grid-cols-[minmax(0,1fr)_minmax(0,3fr)]')}>
      {field.compact ? null : <div className="font-medium text-muted-foreground">{field.label}</div>}
      <div className={cn('min-w-0 break-words', field.textAlign === 'right' && 'text-end', field.textAlign === 'center' && 'text-center')}>
        <DetailField field={field} />
      </div>
    </div>
  );
}
