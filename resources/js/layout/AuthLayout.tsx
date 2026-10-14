import { Head, usePage } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import { Card } from '@lathephp/ui';
import type { ReactNode } from 'react';
import type { SharedProps } from '../types';

const { RenderHook } = __panel;

/** The centred card every sign-in screen sits in: the brand name, one card, the auth:after-form hook. */
export function AuthLayout({ title, children }: { title: string; children: ReactNode }) {
  const { appName } = usePage<SharedProps>().props;

  return (
    <main className="grid min-h-svh place-items-center bg-muted p-6 text-foreground">
      <div className="w-full max-w-sm space-y-6">
        <Head title={title} />
        <RenderHook position="head" />
        <h1 className="text-center text-xl font-semibold">{appName}</h1>
        <Card className="space-y-4 p-6">{children}</Card>
        <RenderHook position="auth:after-form" className="block" />
      </div>
    </main>
  );
}

export const authInputClass =
  'h-9 w-full rounded-md border border-border bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring aria-invalid:border-destructive';
