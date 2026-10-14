import { useForm, usePage } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { OTPField, OTPFieldGroup, OTPFieldSeparator, OTPFieldSlot } from '@lathephp/ui/otp-field';
import { useState, type SubmitEvent } from 'react';
import { FieldError } from '../../components/FieldError';
import { AuthLayout, authInputClass } from '../../layout/AuthLayout';
import type { SharedProps } from '../../types';

interface TwoFactorChallengeProps {
  errors: Partial<Record<'code' | 'recovery_code', string>>;
}

/** A 6-digit authenticator code, one slot per digit. Digits stay left to right in RTL. */
export function TotpInput({ id, value, error, onChange }: { id: string; value: string; error?: string; onChange: (value: string) => void }) {
  const { __ } = useTranslation();
  const slot = (index: number) => <OTPFieldSlot key={index} aria-label={__('Digit :number of 6', { number: index + 1 })} />;

  return (
    <div dir="ltr">
      <OTPField id={id} length={6} validationType="numeric" inputMode="numeric" autoComplete="one-time-code" value={value} onValueChange={onChange}>
        <OTPFieldGroup>
          {/* The first slot takes the field's label from its <label>. */}
          <OTPFieldSlot aria-invalid={error === undefined ? undefined : true} aria-describedby={error === undefined ? undefined : `${id}-error`} />
          {[1, 2].map(slot)}
        </OTPFieldGroup>
        <OTPFieldSeparator />
        <OTPFieldGroup>{[3, 4, 5].map(slot)}</OTPFieldGroup>
      </OTPField>
    </div>
  );
}

/** The second sign-in step for a user with two-factor: an authenticator code, or a recovery code instead. */
export default function TwoFactorChallenge({ errors }: TwoFactorChallengeProps) {
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
  const [recovery, setRecovery] = useState(false);
  const form = useForm({ code: '', recovery_code: '' });

  function submit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    form.transform((data) => (recovery ? { recovery_code: data.recovery_code } : { code: data.code }));
    form.post(`${basePath}/two-factor-challenge`);
  }

  return (
    <AuthLayout title={__('Two-factor authentication')}>
      <p className="text-sm text-muted-foreground">
        {recovery ? __('Enter one of your recovery codes.') : __('Enter the 6-digit code from your authenticator app.')}
      </p>
      <form className="space-y-4" noValidate onSubmit={submit}>
        {recovery ? (
          <div className="space-y-1.5">
            <label htmlFor="recovery_code" className="text-sm font-medium">
              {__('Recovery code')}
            </label>
            <input
              id="recovery_code"
              name="recovery_code"
              type="text"
              dir="ltr"
              autoComplete="one-time-code"
              value={form.data.recovery_code}
              aria-invalid={errors.recovery_code === undefined ? undefined : true}
              aria-describedby={errors.recovery_code === undefined ? undefined : 'recovery_code-error'}
              className={authInputClass}
              onChange={(event) => {
                form.setData('recovery_code', event.target.value);
              }}
            />
            <FieldError id="recovery_code-error" message={errors.recovery_code} />
          </div>
        ) : (
          <div className="space-y-1.5">
            <label htmlFor="code" className="text-sm font-medium">
              {__('Code')}
            </label>
            <TotpInput
              id="code"
              value={form.data.code}
              error={errors.code}
              onChange={(value) => {
                form.setData('code', value);
              }}
            />
            <FieldError id="code-error" message={errors.code} />
          </div>
        )}
        <Button type="submit" className="w-full" disabled={form.processing}>
          {__('Sign in')}
        </Button>
      </form>
      <Button
        type="button"
        variant="link"
        className="w-full"
        onClick={() => {
          setRecovery(!recovery);
        }}
      >
        {recovery ? __('Use an authentication code') : __('Use a recovery code')}
      </Button>
    </AuthLayout>
  );
}
