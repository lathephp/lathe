import { __panel } from '@lathephp/core';
import { Suspense, useEffect, useState } from 'react';
import { lazyNamed } from '../lib/lazy';

// The dialog loads on the first 423, so a page that never needs it never fetches it.
const ConfirmPasswordDialog = lazyNamed(() => import('./ConfirmPasswordDialog').then((module) => module.ConfirmPasswordDialog));

/** Asks for the password when a request answers 423. Requests that wait at the same time share one dialog. */
export function PasswordConfirmHost() {
  const [resolve, setResolve] = useState<((confirmed: boolean) => void) | null>(null);

  useEffect(() => {
    let pending: Promise<boolean> | undefined;

    __panel.setPasswordConfirmer(
      () =>
        (pending ??= new Promise<boolean>((done) => {
          setResolve(() => done);
        }).finally(() => {
          pending = undefined;
        })),
    );

    return () => {
      __panel.setPasswordConfirmer(undefined);
    };
  }, []);

  return resolve === null ? null : (
    <Suspense fallback={null}>
      <ConfirmPasswordDialog
        onDone={(confirmed) => {
          resolve(confirmed);
          setResolve(null);
        }}
      />
    </Suspense>
  );
}
