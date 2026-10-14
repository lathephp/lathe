import { router, usePage } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { useState } from 'react';
import { AuthLayout } from '../../layout/AuthLayout';
import type { SharedProps } from '../../types';

interface VerifyEmailProps {
  sent: boolean;
}

export default function VerifyEmail({ sent }: VerifyEmailProps) {
  const { __ } = useTranslation();
  const { basePath, authPaths } = usePage<SharedProps>().props;
  const [busy, setBusy] = useState(false);

  return (
    <AuthLayout title={__('Verify email')}>
      <p className="text-sm text-muted-foreground">
        {__('Before you go on, please verify your email address by following the link we emailed to you.')}
      </p>
      {sent && (
        <p role="status" className="text-sm font-medium">
          {__('A new verification link has been sent to your email address.')}
        </p>
      )}
      <Button
        className="w-full"
        disabled={busy}
        onClick={() => {
          router.post(`${basePath}/email/verification-notification`, {}, {
            onStart: () => {
              setBusy(true);
            },
            onFinish: () => {
              setBusy(false);
            },
          });
        }}
      >
        {__('Resend verification email')}
      </Button>
      <Button
        variant="outline"
        className="w-full"
        onClick={() => {
          router.post(authPaths.logout ?? `${basePath}/logout`);
        }}
      >
        {__('Sign out')}
      </Button>
    </AuthLayout>
  );
}
