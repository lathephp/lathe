import { useTranslation } from '@lathephp/core';
import { OTPField, OTPFieldGroup, OTPFieldSeparator, OTPFieldSlot } from '@lathephp/ui/otp-field';

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
