import { Upload } from 'lucide-react';
import { Button, type ButtonProps } from './Button';

export interface FilePickerProps extends Omit<ButtonProps, 'type' | 'onClick' | 'onChange'> {
  /** The file types the dialog offers, as the accept attribute of a file input takes them. */
  accept?: string;
  onPick: (file: File) => void;
}

/**
 * A button that opens the system file dialog. The file input lives only in code for the
 * moment of the press, so the page holds no browser-drawn file control.
 */
export function FilePicker({ accept, onPick, children, variant = 'outline', size = 'sm', ...props }: FilePickerProps) {
  return (
    <Button
      variant={variant}
      size={size}
      {...props}
      onClick={() => {
        const input = document.createElement('input');
        input.type = 'file';

        if (accept !== undefined) {
          input.accept = accept;
        }

        input.onchange = () => {
          const file = input.files?.[0];

          if (file !== undefined) {
            onPick(file);
          }
        };
        input.click();
      }}
    >
      <Upload aria-hidden="true" className="size-4" />
      {children}
    </Button>
  );
}
