import { useForm, usePage } from '@inertiajs/react';
import { __panel, useTranslation } from '@lathephp/core';
import { Button, Checkbox } from '@lathephp/ui';
import type { SubmitEvent } from 'react';
import { FieldError } from '../../components/FieldError';
import { AuthLayout } from '../../layout/AuthLayout';
import type { SharedProps } from '../../types';
import { Input } from '../../components/ui/input';

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
                <Input
                  id="email"
                  name="email"
                  type={byEmail ? 'email' : 'text'}
                  autoComplete="username"
                  required
                  value={form.data.email}
                  aria-invalid={errors.email === undefined ? undefined : true}
                  aria-describedby={errors.email === undefined ? undefined : 'email-error'}
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
                <Input
                  id="password"
                  name="password"
                  type="password"
                  autoComplete="current-password"
                  required
                  value={form.data.password}
                  aria-invalid={errors.password === undefined ? undefined : true}
                  aria-describedby={errors.password === undefined ? undefined : 'password-error'}
                  onChange={(event) => {
                    form.setData('password', event.target.value);
                  }}
                />
                <FieldError id="password-error" message={errors.password} />
              </div>
              <div className="flex items-center gap-2">
                <Checkbox
                  id="remember"
                  name="remember"
                  checked={form.data.remember}
                  onCheckedChange={(checked) => {
                    form.setData('remember', checked);
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
