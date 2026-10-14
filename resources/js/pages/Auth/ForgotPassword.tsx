import { useForm, usePage } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import type { SubmitEvent } from 'react';
import { FieldError } from '../../components/FieldError';
import { AuthLayout, authInputClass } from '../../layout/AuthLayout';
import type { SharedProps } from '../../types';

interface ForgotPasswordProps {
  status: string | null;
  errors: Partial<Record<'email', string>>;
}

export default function ForgotPassword({ status, errors }: ForgotPasswordProps) {
  const { __ } = useTranslation();
  const { basePath, authPaths } = usePage<SharedProps>().props;
  const form = useForm({ email: '' });

  function submit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    form.post(`${basePath}/forgot-password`);
  }

  return (
    <AuthLayout title={__('Forgot password')}>
      <p className="text-sm text-muted-foreground">{__('Enter your email address and we will email you a link to reset your password.')}</p>
      {status !== null && (
        <p role="status" className="text-sm font-medium">
          {status}
        </p>
      )}
      <form className="space-y-4" noValidate onSubmit={submit}>
        <div className="space-y-1.5">
          <label htmlFor="email" className="text-sm font-medium">
            {__('Email')}
          </label>
          <input
            id="email"
            name="email"
            type="email"
            autoComplete="email"
            required
            value={form.data.email}
            aria-invalid={errors.email === undefined ? undefined : true}
            aria-describedby={errors.email === undefined ? undefined : 'email-error'}
            className={authInputClass}
            onChange={(event) => {
              form.setData('email', event.target.value);
            }}
          />
          <FieldError id="email-error" message={errors.email} />
        </div>
        <Button type="submit" className="w-full" disabled={form.processing}>
          {__('Email password reset link')}
        </Button>
      </form>
      {authPaths.login !== null && (
        <a href={authPaths.login} className="block text-center text-sm underline-offset-4 hover:underline">
          {__('Back to sign in')}
        </a>
      )}
    </AuthLayout>
  );
}
