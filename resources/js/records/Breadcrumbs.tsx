import { Link } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { Fragment } from 'react';
import type { Breadcrumb } from '../fields/types';

export function Breadcrumbs({ items }: { items: Breadcrumb[] }) {
  const { __ } = useTranslation();

  return (
    <nav aria-label={__('Breadcrumb')} className="text-sm text-muted-foreground">
      <ol className="flex flex-wrap items-center gap-1.5">
        {items.map((item, index) => (
          <Fragment key={`${item.label}-${String(index)}`}>
            {index > 0 ? <li aria-hidden="true">/</li> : null}
            <li>
              {item.href === null ? (
                <span aria-current="page" className="text-foreground">
                  {item.label}
                </span>
              ) : (
                <Link href={item.href} className="hover:text-foreground">
                  {item.label}
                </Link>
              )}
            </li>
          </Fragment>
        ))}
      </ol>
    </nav>
  );
}
