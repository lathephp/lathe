import { createContext, use, type ReactNode } from 'react';
import { createPortal } from 'react-dom';

/** The phone top bar's slot for the page title and its primary action; the shell provides it. */
export const PageBar = createContext<HTMLElement | null>(null);

interface PageHeaderProps {
  title: ReactNode;
  /** A status badge beside the title. */
  badge?: ReactNode;
  /** The muted line under the title. */
  subtitle?: ReactNode;
  /** The buttons on the right, with at most one primary. */
  actions?: ReactNode;
  /** The icon-only primary the phone's top bar shows. */
  phoneAction?: ReactNode;
}

/** A page's one-row header: the title on the left, the actions on the right. A phone shows the title in its top bar. */
export function PageHeader({ title, badge, subtitle, actions, phoneAction }: PageHeaderProps) {
  // Every page renders it once per visit, so the compiler's cache would only add first-load bytes.
  'use no memo';

  const bar = use(PageBar);

  return (
    <div data-lathe-header="" className="lathe-page-header">
      <div className="lathe-page-heading">
        <div className="flex min-w-0 items-center gap-2.5">
          <h1 className="lathe-page-title">{title}</h1>
          {badge}
        </div>
        {subtitle}
      </div>
      <div className="lathe-page-actions">{actions}</div>
      {bar !== null &&
        createPortal(
          <>
            <span aria-hidden="true" className="lathe-page-bar-title">
              {title}
            </span>
            {phoneAction}
          </>,
          bar,
        )}
    </div>
  );
}
