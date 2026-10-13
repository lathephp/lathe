import { toast, useTranslation } from '@lathephp/core';
import { Copy } from 'lucide-react';

/** Copy a text to the clipboard and say whether it worked. */
export function CopyButton({ text }: { text: string }) {
  const { __ } = useTranslation();

  return (
    <button
      type="button"
      aria-label={__('Copy')}
      className="inline-flex size-6 shrink-0 items-center justify-center rounded text-muted-foreground outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
      onClick={(event) => {
        event.stopPropagation();
        navigator.clipboard.writeText(text).then(
          () => {
            toast.success(__('Copied to clipboard'));
          },
          () => {
            toast.error(__('Copy failed'));
          },
        );
      }}
    >
      <Copy aria-hidden="true" className="size-3.5" />
    </button>
  );
}
