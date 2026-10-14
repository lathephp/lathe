import { useForm, usePage } from '@inertiajs/react';
import { __panel, useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import type { SubmitEvent } from 'react';
import { FieldError } from '../../components/FieldError';
import { AuthLayout, authInputClass as inputClass } from '../../layout/AuthLayout';
import type { SharedProps } from '../../types';

interface LoginProps {
  username: string;
  status?: string | null;
  errors: Partial<Record<'email' | 'password', string>>;
}

const { Slot } = __panel;

export default function Login({ username, status = null, errors }: LoginProps) {
  const { __ } = useTranslation();
  const { basePath, authPaths } = usePage<SharedProps>().props;
  const form = useForm({ email: '', password: '', remember: false });
  const byEmail = username === 'email';

  function submit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    // A failed sign-in leaves no password in the field.
    form.post(`${basePath}/login`, {
      onFinish: () => {
        form.reset('password');
      },
    });
  }

  return (
    <Slot name="auth.login" username={username} errors={errors}>
      <AuthLayout title={__('Sign in')}>
        {status !== null && (
          <p role="status" className="text-sm font-medium">
            {status}
          </p>
        )}
            <form className="space-y-4" noValidate onSubmit={submit}>
              <div className="space-y-1.5">
                <label htmlFor="email" className="text-sm font-medium">
                  {byEmail ? __('Email') : __('Username')}
                </label>
                <input
                  id="email"
                  name="email"
                  type={byEmail ? 'email' : 'text'}
                  autoComplete="username"
                  required
                  value={form.data.email}
                  aria-invalid={errors.email === undefined ? undefined : true}
                  aria-describedby={errors.email === undefined ? undefined : 'email-error'}
                  className={inputClass}
                  onChange={(event) => {
                    form.setData('email', event.target.value);
                  }}
                />
                <FieldError id="email-error" message={errors.email} />
              </div>
              <div className="space-y-1.5">
                <label htmlFor="password" className="text-sm font-medium">
                  {__('Password')}
                </label>
                <input
                  id="password"
                  name="password"
                  type="password"
                  autoComplete="current-password"
                  required
                  value={form.data.password}
                  aria-invalid={errors.password === undefined ? undefined : true}
                  aria-describedby={errors.password === undefined ? undefined : 'password-error'}
                  className={inputClass}
                  onChange={(event) => {
                    form.setData('password', event.target.value);
                  }}
                />
                <FieldError id="password-error" message={errors.password} />
              </div>
              <div className="flex items-center gap-2">
                <input
                  id="remember"
                  name="remember"
                  type="checkbox"
                  checked={form.data.remember}
                  className="size-4 rounded border-border focus-visible:ring-2 focus-visible:ring-ring"
                  onChange={(event) => {
                    form.setData('remember', event.target.checked);
                  }}
                />
                <label htmlFor="remember" className="text-sm">
                  {__('Remember me')}
                </label>
              </div>
              <Button type="submit" className="w-full" disabled={form.processing}>
                {__('Sign in')}
              </Button>
            </form>
        {authPaths.forgotPassword !== null && (
          <a href={authPaths.forgotPassword} className="block text-center text-sm underline-offset-4 hover:underline">
            {__('Forgot your password?')}
          </a>
        )}
      </AuthLayout>
    </Slot>
  );
}
