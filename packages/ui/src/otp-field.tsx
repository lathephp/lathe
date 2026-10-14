import { OTPField as OTPFieldPrimitive } from '@base-ui/react/otp-field';
import type { ComponentProps } from 'react';
import { MinusIcon } from './lib/icons';
import { cn } from './lib/utils';

/** A one-time code entered one character per slot. */
function OTPField({ className, ...props }: OTPFieldPrimitive.Root.Props) {
  return (
    <OTPFieldPrimitive.Root
      data-slot="otp-field"
      className={cn('cn-input-otp flex items-center has-disabled:opacity-50', className)}
      {...props}
    />
  );
}

function OTPFieldGroup({ className, ...props }: ComponentProps<'div'>) {
  return (
    <div data-slot="otp-field-group" className={cn('cn-input-otp-group flex items-center', className)} {...props} />
  );
}

function OTPFieldSlot({ className, ...props }: OTPFieldPrimitive.Input.Props) {
  return (
    <OTPFieldPrimitive.Input
      data-slot="otp-field-slot"
      className={cn(
        'cn-input-otp-slot relative bg-transparent text-center focus:z-10 focus:border-ring focus:ring-3 focus:ring-ring/50 disabled:cursor-not-allowed',
        className,
      )}
      {...props}
    />
  );
}

function OTPFieldSeparator({ className, ...props }: ComponentProps<typeof OTPFieldPrimitive.Separator>) {
  return (
    <OTPFieldPrimitive.Separator
      data-slot="otp-field-separator"
      className={cn('cn-input-otp-separator flex items-center', className)}
      {...props}
    >
      <MinusIcon />
    </OTPFieldPrimitive.Separator>
  );
}

export { OTPField, OTPFieldGroup, OTPFieldSeparator, OTPFieldSlot };
