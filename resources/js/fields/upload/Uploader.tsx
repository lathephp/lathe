import { useTranslation } from '@lathephp/core';
import type { FormFieldData } from '../types';
import { useChunkedUpload } from './useChunkedUpload';

function acceptAttribute(types: unknown): string | undefined {
  if (!Array.isArray(types) || types.length === 0) {
    return undefined;
  }

  return types
    .filter((type): type is string => typeof type === 'string')
    .map((type) => (type.includes('/') || type.startsWith('.') ? type : `.${type}`))
    .join(',');
}

/**
 * The file input of a file field: it uploads the chosen file in chunks with a progress bar
 * and hands the upload id to the form. `attribute` is the form path, such as `items.0.photo`.
 */
export function Uploader({
  field,
  attribute,
  onChange,
  id,
  describedBy,
  invalid,
  disabled,
}: {
  field: FormFieldData;
  attribute: string;
  onChange: (value: unknown) => void;
  id: string;
  describedBy: string | undefined;
  invalid: boolean;
  disabled: boolean;
}) {
  const { __ } = useTranslation();
  const upload = useChunkedUpload(field, attribute, onChange);
  const errorId = `${id}-upload-error`;

  return (
    <div className="flex flex-col gap-1">
      <input
        id={id}
        type="file"
        accept={acceptAttribute(field.acceptedTypes)}
        aria-describedby={[describedBy, upload.error === null ? undefined : errorId].filter(Boolean).join(' ') || undefined}
        aria-invalid={invalid || upload.error !== null || undefined}
        disabled={disabled}
        onChange={(event) => {
          const file = event.target.files?.[0];

          if (file !== undefined) {
            void upload.choose(file);
          }
        }}
        className="text-sm file:me-3 file:rounded-md file:border file:border-input file:bg-background file:px-3 file:py-1 file:text-sm"
      />
      {upload.progress === null ? null : (
        <div className="flex items-center gap-2 text-xs text-muted-foreground">
          <div
            role="progressbar"
            aria-label={upload.name ?? __('Upload')}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={upload.progress}
            className="h-1.5 w-full max-w-64 overflow-hidden rounded-full bg-muted"
          >
            <div className="h-full bg-primary transition-[width]" style={{ width: `${String(upload.progress)}%` }} />
          </div>
          <span>{upload.progress === 100 ? __('Uploaded') : `${String(upload.progress)}%`}</span>
        </div>
      )}
      {upload.error === null ? null : (
        <p id={errorId} role="alert" className="text-xs text-destructive">
          {upload.error}
        </p>
      )}
    </div>
  );
}
