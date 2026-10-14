import { useForm, usePage } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import type { SubmitEvent } from 'react';
import { FieldError } from '../../components/FieldError';
import { AuthLayout, authInputClass } from '../../layout/AuthLayout';
import type { SharedProps } from '../../types';

interface ResetPasswordProps {
  token: string;
  email: string;
  errors: Partial<Record<'email' | 'password', string>>;
}

export default function ResetPassword({ token, email, errors }: ResetPasswordProps) {
  const { __ } = useTranslation();
  const { basePath, authPaths } = usePage<SharedProps>().props;
  const form = useForm({ token, email, password: '', password_confirmation: '' });

  function submit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    form.post(`${basePath}/reset-password`, {
      onFinish: () => {
        form.reset('password', 'password_confirmation');
      },
    });
  }

  return (
    <AuthLayout title={__('Reset password')}>
      <form className="space-y-4" noValidate onSubmit={submit}>
        <div className="space-y-1.5">
          <label htmlFor="email" className="text-sm font-medium">
            {__('Email')}
          </label>
          <input
            id="email"
            name="email"
            type="email"
            autoComplete="username"
            readOnly
            value={form.data.email}
            aria-invalid={errors.email === undefined ? undefined : true}
            aria-describedby={errors.email === undefined ? undefined : 'email-error'}
            className={`${authInputClass} bg-muted`}
          />
          <FieldError id="email-error" message={errors.email} />
          {/* An expired or used link: the way out is a new one. */}
          {errors.email !== undefined && authPaths.forgotPassword !== null && (
            <a href={authPaths.forgotPassword} className="text-sm underline underline-offset-4">
              {__('Send a new reset link')}
            </a>
          )}
        </div>
        <div className="space-y-1.5">
          <label htmlFor="password" className="text-sm font-medium">
            {__('New password')}
          </label>
          <input
            id="password"
            name="password"
            type="password"
            autoComplete="new-password"
            required
            value={form.data.password}
            aria-invalid={errors.password === undefined ? undefined : true}
            aria-describedby={errors.password === undefined ? undefined : 'password-error'}
            className={authInputClass}
            onChange={(event) => {
              form.setData('password', event.target.value);
            }}
          />
          <FieldError id="password-error" message={errors.password} />
        </div>
        <div className="space-y-1.5">
          <label htmlFor="password_confirmation" className="text-sm font-medium">
            {__('Confirm password')}
          </label>
          <input
            id="password_confirmation"
            name="password_confirmation"
            type="password"
            autoComplete="new-password"
            required
            value={form.data.password_confirmation}
            className={authInputClass}
            onChange={(event) => {
              form.setData('password_confirmation', event.target.value);
            }}
          />
        </div>
        <Button type="submit" className="w-full" disabled={form.processing}>
          {__('Reset password')}
        </Button>
      </form>
    </AuthLayout>
  );
}
