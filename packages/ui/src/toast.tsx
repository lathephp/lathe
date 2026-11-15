import { Toast as ToastPrimitive } from '@base-ui/react/toast';
import { cn } from './lib/utils';

const createToastManager = ToastPrimitive.createToastManager;
const useToastManager = ToastPrimitive.useToastManager;

// Three at once by default; the timers pause while the pointer or focus is on a toast.
function ToastProvider({ limit = 3, ...props }: ToastPrimitive.Provider.Props) {
  return <ToastPrimitive.Provider limit={limit} {...props} />;
}

/** The stack at the bottom end of the screen, centred along the bottom of a phone, above every other layer. */
function ToastViewport({ className, ...props }: ToastPrimitive.Viewport.Props) {
  return (
    <ToastPrimitive.Portal>
      <ToastPrimitive.Viewport
        data-slot="toast-viewport"
        className={cn('pointer-events-none fixed inset-x-4 bottom-4 z-80 mx-auto w-auto max-w-sm outline-none sm:end-4 sm:start-auto sm:mx-0 sm:w-full', className)}
        {...props}
      />
    </ToastPrimitive.Portal>
  );
}

/** The card; the style's cn-toast sets its corners. Base UI's index, offset and swipe variables place it in the stack. */
function Toast({ className, ...props }: ToastPrimitive.Root.Props) {
  return (
    <ToastPrimitive.Root
      data-slot="toast"
      className={cn(
        // shadcn's stack: the newest card in front, the older ones peeking 12 px above it and 10% smaller each; it fans out on hover or focus.
        'cn-toast group/toast pointer-events-auto absolute end-0 bottom-0 z-[calc(1000-var(--toast-index))] w-full origin-bottom border border-border bg-popover text-popover-foreground shadow-lg will-change-transform outline-none select-none focus-visible:ring-2 focus-visible:ring-focus',
        '[--gap:0.75rem] [--height:var(--toast-frontmost-height,var(--toast-height))] [--offset-y:calc(var(--toast-offset-y)*-1+calc(var(--toast-index)*var(--gap)*-1)+var(--toast-swipe-movement-y))] [--peek:0.75rem] [--scale:calc(max(0,1-(var(--toast-index)*0.1)))] [--shrink:calc(1-var(--scale))]',
        'h-(--height) [transform:translateX(var(--toast-swipe-movement-x))_translateY(calc(var(--toast-swipe-movement-y)-(var(--toast-index)*var(--peek))-(var(--shrink)*var(--height))))_scale(var(--scale))] [transition:transform_500ms_cubic-bezier(0.22,1,0.36,1),opacity_500ms,height_150ms]',
        "after:absolute after:top-full after:start-0 after:h-[calc(var(--gap)+1px)] after:w-full after:content-['']",
        'data-expanded:h-(--toast-height) data-expanded:[transform:translateX(var(--toast-swipe-movement-x))_translateY(var(--offset-y))]',
        'data-limited:opacity-0 data-starting-style:[transform:translateY(150%)]',
        '[&[data-ending-style]:not([data-limited]):not([data-swipe-direction])]:[transform:translateY(150%)]',
        'data-ending-style:data-[swipe-direction=down]:[transform:translateY(calc(var(--toast-swipe-movement-y)+150%))]',
        'data-ending-style:data-[swipe-direction=left]:[transform:translateX(calc(var(--toast-swipe-movement-x)-150%))_translateY(var(--offset-y))]',
        'data-ending-style:data-[swipe-direction=right]:[transform:translateX(calc(var(--toast-swipe-movement-x)+150%))_translateY(var(--offset-y))]',
        'data-ending-style:data-[swipe-direction=up]:[transform:translateY(calc(var(--toast-swipe-movement-y)-150%))]',
        'data-expanded:data-ending-style:data-[swipe-direction=down]:[transform:translateY(calc(var(--toast-swipe-movement-y)+150%))]',
        'data-expanded:data-ending-style:data-[swipe-direction=left]:[transform:translateX(calc(var(--toast-swipe-movement-x)-150%))_translateY(var(--offset-y))]',
        'data-expanded:data-ending-style:data-[swipe-direction=right]:[transform:translateX(calc(var(--toast-swipe-movement-x)+150%))_translateY(var(--offset-y))]',
        'data-expanded:data-ending-style:data-[swipe-direction=up]:[transform:translateY(calc(var(--toast-swipe-movement-y)-150%))]',
        className,
      )}
      {...props}
    />
  );
}

/** The row inside the card: the kind's icon, the text, then the action and the close button. It fades out behind the front card. */
function ToastContent({ className, ...props }: ToastPrimitive.Content.Props) {
  return (
    <ToastPrimitive.Content
      data-slot="toast-content"
      className={cn('flex h-full items-center gap-3 overflow-hidden p-4 transition-opacity duration-250 ease-[cubic-bezier(0.22,1,0.36,1)] data-behind:opacity-0 data-expanded:opacity-100', className)}
      {...props}
    />
  );
}

function ToastTitle({ className, ...props }: ToastPrimitive.Title.Props) {
  return <ToastPrimitive.Title data-slot="toast-title" className={cn('text-sm leading-5 font-medium', className)} {...props} />;
}

function ToastDescription({ className, ...props }: ToastPrimitive.Description.Props) {
  return <ToastPrimitive.Description data-slot="toast-description" className={cn('text-sm leading-5 text-muted-foreground', className)} {...props} />;
}

export { createToastManager, Toast, ToastContent, ToastDescription, ToastProvider, ToastTitle, ToastViewport, useToastManager };
