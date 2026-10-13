import { useTranslation } from '@lathephp/core';
import { Badge } from '@lathephp/ui';

export default function Welcome() {
  const { __ } = useTranslation();

  return (
    <main>
      <h1>{__('Lathe')}</h1>
      <Badge>{__('Ready')}</Badge>
    </main>
  );
}
