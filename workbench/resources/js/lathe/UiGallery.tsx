import { useTranslation } from '@lathephp/core';

/** The page that will show every @lathephp/ui component. */
export default function UiGallery() {
  const { __ } = useTranslation();

  return (
    <section>
      <h1 className="text-2xl font-semibold">{__('UI Gallery')}</h1>
      <p className="mt-2 text-sm text-muted-foreground">{__('Every @lathephp/ui component on one page.')}</p>
    </section>
  );
}
