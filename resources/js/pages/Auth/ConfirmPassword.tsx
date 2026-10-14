import { useForm, usePage } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import type { SubmitEvent } from 'react';
import { FieldError } from '../../components/FieldError';
import { AuthLayout, authInputClass } from '../../layout/AuthLayout';
import type { SharedProps } from '../../types';

interface ConfirmPasswordProps {
  errors: Partial<Record<'password', string>>;
}

/** The full page a browser visit behind RequirePassword lands on; the server then sends it on to the page it wanted. */
export default function ConfirmPassword({ errors }: ConfirmPasswordProps) {
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
  const form = useForm({ password: '' });

  function submit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    form.post(`${basePath}/confirm-password`);
  }

  return (
    <AuthLayout title={__('Confirm password')}>
      <p className="text-sm text-muted-foreground">{__('For your security, please confirm your password to continue.')}</p>
      <form className="space-y-4" noValidate onSubmit={submit}>
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
            className={authInputClass}
            onChange={(event) => {
              form.setData('password', event.target.value);
            }}
          />
          <FieldError id="password-error" message={errors.password} />
        </div>
        <Button type="submit" className="w-full" disabled={form.processing}>
          {__('Confirm')}
        </Button>
      </form>
    </AuthLayout>
  );
}
