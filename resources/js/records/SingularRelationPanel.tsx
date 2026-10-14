import { Link, usePage } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { Card } from '../components/ui/Card';
import type { DetailFieldData } from '../fields/types';
import type { SharedProps } from '../types';

interface RelatedRecord {
  id: string | number;
  title: string;
  href: string | null;
}

function relatedRecord(value: unknown): RelatedRecord | null {
  return typeof value === 'object' && value !== null && 'id' in value && 'title' in value ? (value as RelatedRecord) : null;
}

/**
 * The detail block of a hasOne or morphOne field: the related record with a link to its page,
 * or a Create button under the via when the parent has none and the user may add one.
 */
export function SingularRelationPanel({ field }: { field: DetailFieldData }) {
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
  const record = relatedRecord(field.record);
  const label = typeof field.singularLabel === 'string' ? field.singularLabel : field.label;
  const createHref =
    typeof field.resource === 'string' && typeof field.via === 'string'
      ? `${basePath}/resources/${field.resource}/new?${new URLSearchParams({ via: field.via }).toString()}`
      : null;

  return (
    <section aria-label={field.label} className="space-y-2">
      <h2 className="text-base font-semibold">{field.label}</h2>
      <Card className="flex items-center justify-between gap-4 px-4 py-3 text-sm">
        {record === null ? (
          <>
            <span className="text-muted-foreground">{__('No :resource yet', { resource: label })}</span>
            {field.canAdd === true && createHref !== null ? (
              <Link
                href={createHref}
                className="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground"
              >
                {__('Create :resource', { resource: label })}
              </Link>
            ) : null}
          </>
        ) : record.href === null ? (
          <span>{record.title}</span>
        ) : (
          <Link href={record.href} className="text-primary hover:underline">
            {record.title}
          </Link>
        )}
      </Card>
    </section>
  );
}
