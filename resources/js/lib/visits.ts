import { toast } from '@lathephp/core';
import type { SharedProps } from '../types';

/** The server's flash messages of one visit become toasts. */
export function showFlash(flash: SharedProps['flash'] | undefined): void {
  if (flash?.success != null && flash.success !== '') {
    toast.success(flash.success);
  }

  if (flash?.error != null && flash.error !== '') {
    toast.error(flash.error);
  }
}
