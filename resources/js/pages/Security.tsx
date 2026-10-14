import { Head, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { Button, Card, CardContent, CardDescription, CardHeader, CardTitle } from '@lathephp/ui';
import type { SubmitEvent } from 'react';
import { FieldError } from '../components/FieldError';
import { authInputClass } from '../layout/AuthLayout';
import type { SharedProps } from '../types';

interface SecurityProps {
  features: { updatePasswords: boolean; twoFactorAuthentication: boolean };
}

type PasswordErrors = Partial<Record<'current_password' | 'password', string>>;

function PasswordField({ id, label, autoComplete, value, error, onChange }: { id: string; label: string; autoComplete: string; value: string; error?: string; onChange: (value: string) => void }) {
  return (
    <div className="space-y-1.5">
      <label htmlFor={id} className="text-sm font-medium">
        {label}
      </label>
      <input
        id={id}
        name={id}
        type="password"
        autoComplete={autoComplete}
        value={value}
        aria-invalid={error === undefined ? undefined : true}
        aria-describedby={error === undefined ? undefined : `${id}-error`}
        className={authInputClass}
        onChange={(event) => {
          onChange(event.target.value);
        }}
      />
      <FieldError id={`${id}-error`} message={error} />
    </div>
  );
}

function PasswordCard() {
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
  const form = useForm({ current_password: '', password: '', password_confirmation: '' });
  const errors: PasswordErrors = form.errors;

  function submit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    form.put(`${basePath}/user-security/password`, {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
      },
      onError: () => {
        form.reset('password', 'password_confirmation');
      },
    });
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{__('Password')}</CardTitle>
        <CardDescription>{__('Use a long, random password to keep your account safe.')}</CardDescription>
      </CardHeader>
      <CardContent>
        <form className="max-w-sm space-y-4" noValidate onSubmit={submit}>
          <PasswordField
            id="current_password"
            label={__('Current password')}
            autoComplete="current-password"
            value={form.data.current_password}
            error={errors.current_password}
            onChange={(value) => {
              form.setData('current_password', value);
            }}
          />
          <PasswordField
            id="password"
            label={__('New password')}
            autoComplete="new-password"
            value={form.data.password}
            error={errors.password}
            onChange={(value) => {
              form.setData('password', value);
            }}
          />
          <PasswordField
            id="password_confirmation"
            label={__('Confirm password')}
            autoComplete="new-password"
            value={form.data.password_confirmation}
            onChange={(value) => {
              form.setData('password_confirmation', value);
            }}
          />
          <Button type="submit" disabled={form.processing}>
            {__('Update password')}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}

/** Password and two-factor settings for the signed-in user. */
export default function Security({ features }: SecurityProps) {
  const { __ } = useTranslation();

  return (
    <div className="mx-auto w-full max-w-3xl space-y-6 p-6">
      <Head title={__('Security')} />
      <h1 className="text-2xl font-semibold">{__('Security')}</h1>
      {features.updatePasswords && <PasswordCard />}
    </div>
  );
}
