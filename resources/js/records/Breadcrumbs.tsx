import { Link } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { ChevronRight } from 'lucide-react';
import type { Breadcrumb } from '../fields/types';

/** The trail in the top bar: links and menu groups muted, the last crumb without a link is the current page. */
export function Breadcrumbs({ items }: { items: Breadcrumb[] }) {
  // It renders once per visit, so the compiler's cache would only add first-load bytes.
  'use no memo';

  const { __ } = useTranslation();

  if (items.length === 0) {
    return null;
  }

  return (
    <nav aria-label={__('Breadcrumb')} className="lathe-crumbs">
      <ol>
        {items.map((item, index) => (
          <li key={`${item.label}-${String(index)}`}>
            {index > 0 && <ChevronRight aria-hidden="true" className="size-3.5 rtl:rotate-180" />}
            {item.href === null ? (
              <span aria-current={index === items.length - 1 ? 'page' : undefined}>{item.label}</span>
            ) : (
              <Link href={item.href}>{item.label}</Link>
            )}
          </li>
        ))}
      </ol>
    </nav>
  );
}
