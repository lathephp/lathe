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

/** The card; the style's cn-toast sets its corners. */
function Toast({ className, ...props }: ToastPrimitive.Root.Props) {
  return (
    <ToastPrimitive.Root
      data-slot="toast"
      className={cn(
        'cn-toast border border-border bg-popover text-sm text-popover-foreground shadow-lg outline-none focus-visible:ring-2 focus-visible:ring-focus data-limited:hidden',
        className,
      )}
      {...props}
    />
  );
}

/** The row inside the card: the kind's icon, the text, then the action and the close button. */
function ToastContent({ className, ...props }: ToastPrimitive.Content.Props) {
  return <ToastPrimitive.Content data-slot="toast-content" className={cn('flex items-center gap-3 p-4', className)} {...props} />;
}

function ToastTitle({ className, ...props }: ToastPrimitive.Title.Props) {
  return <ToastPrimitive.Title data-slot="toast-title" className={cn('text-sm leading-5 font-medium', className)} {...props} />;
}

function ToastDescription({ className, ...props }: ToastPrimitive.Description.Props) {
  return <ToastPrimitive.Description data-slot="toast-description" className={cn('text-sm leading-5 text-muted-foreground', className)} {...props} />;
}

export { createToastManager, Toast, ToastContent, ToastDescription, ToastProvider, ToastTitle, ToastViewport, useToastManager };
