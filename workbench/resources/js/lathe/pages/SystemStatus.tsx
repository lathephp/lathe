import { useTranslation } from '@lathephp/core';

/** The page that will show the server facts for whoever may view system status. */
export default function SystemStatus() {
  const { __ } = useTranslation();

  return (
    <section>
      <h1 className="text-2xl font-semibold">{__('System Status')}</h1>
      <p className="mt-2 text-sm text-muted-foreground">{__('Server facts for whoever may view system status.')}</p>
    </section>
  );
}
