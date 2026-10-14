import { plugin } from '@lathephp/core';
import { Card } from '@lathephp/ui';

function Page() {
  return (
    <Card className="p-6">
      <h1 className="font-semibold leading-none tracking-tight">{{ title }}</h1>
      <p className="mt-2 text-sm text-muted-foreground">Edit resources/js/plugin.tsx to build this page.</p>
    </Card>
  );
}

plugin('{{ component }}', Page);
