import { Head, useForm, usePage } from '@inertiajs/react';
import { __panel, useTranslation } from '@lathephp/core';
import { Button, Card } from '@lathephp/ui';
import type { SubmitEvent } from 'react';
import { FieldError } from '../../components/FieldError';
import type { SharedProps } from '../../types';

interface LoginProps {
  username: string;
  errors: Partial<Record<'email' | 'password', string>>;
}

const { RenderHook, Slot } = __panel;

const inputClass =
  'h-9 w-full rounded-md border border-border bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring aria-invalid:border-destructive';

export default function Login({ username, errors }: LoginProps) {
  const { __ } = useTranslation();
  const { appName, basePath } = usePage<SharedProps>().props;
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
      <main className="grid min-h-svh place-items-center bg-muted p-6 text-foreground">
        <div className="w-full max-w-sm space-y-6">
          <Head title={__('Sign in')} />
          <RenderHook position="head" />
          <h1 className="text-center text-xl font-semibold">{appName}</h1>
          <Card className="p-6">
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
          </Card>
          <RenderHook position="auth:after-form" className="block" />
        </div>
      </main>
    </Slot>
  );
}
