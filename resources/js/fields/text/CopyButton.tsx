import { toast, useTranslation } from '@lathephp/core';
import { Copy } from 'lucide-react';
import { Button } from '../../components/ui/Button';

/** Copy a text to the clipboard and say whether it worked. */
export function CopyButton({ text }: { text: string }) {
  const { __ } = useTranslation();

  return (
    <Button
      variant="ghost"
      size="icon-xs"
      aria-label={__('Copy')}
      className="text-muted-foreground"
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
    </Button>
  );
}
