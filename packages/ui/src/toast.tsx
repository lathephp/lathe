import { Toast as ToastPrimitive } from '@base-ui/react/toast';
import { cn } from './lib/utils';

const createToastManager = ToastPrimitive.createToastManager;
const useToastManager = ToastPrimitive.useToastManager;

// Three at once by default; the timers pause while the pointer or focus is on a toast.
function ToastProvider({ limit = 3, ...props }: ToastPrimitive.Provider.Props) {
  return <ToastPrimitive.Provider limit={limit} {...props} />;
}

/** The stack at the bottom end of the screen, above every other layer. */
function ToastViewport({ className, ...props }: ToastPrimitive.Viewport.Props) {
  return (
    <ToastPrimitive.Portal>
      <ToastPrimitive.Viewport
        data-slot="toast-viewport"
        className={cn('fixed end-4 bottom-4 z-80 flex w-90 max-w-[calc(100vw-2rem)] flex-col-reverse gap-2 outline-none', className)}
        {...props}
      />
    </ToastPrimitive.Portal>
  );
}

function Toast({ className, ...props }: ToastPrimitive.Root.Props) {
  return (
    <ToastPrimitive.Root
      data-slot="toast"
      className={cn(
        'flex items-start gap-2.5 rounded-xl border border-border bg-popover py-3 ps-3.5 pe-3 text-sm text-popover-foreground shadow-md outline-none focus-visible:ring-2 focus-visible:ring-focus data-limited:hidden',
        className,
      )}
      {...props}
    />
  );
}

function ToastDescription({ className, ...props }: ToastPrimitive.Description.Props) {
  return <ToastPrimitive.Description data-slot="toast-description" className={cn('leading-5 font-medium', className)} {...props} />;
}

export { createToastManager, Toast, ToastDescription, ToastProvider, ToastViewport, useToastManager };
