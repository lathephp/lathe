import { plugin } from '@lathephp/core';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@lathephp/ui';

function Page() {
  return (
    <Card>
      <CardHeader>
        <CardTitle>{{ title }}</CardTitle>
        <CardDescription>Edit resources/js/plugin.tsx to build this page.</CardDescription>
      </CardHeader>
      <CardContent>{/* Your plugin page content */}</CardContent>
    </Card>
  );
}

plugin('{{ component }}', Page);
