import { useTranslation } from '@lathephp/core';
import { ImageOff } from 'lucide-react';
import type { FormFieldProps } from '../registry';
import { Uploader } from './Uploader';
import type { FieldSchema } from '../types';

/** The stored file as the server resolves it. */
export interface FileData {
  path: string;
  name: string;
  size: number | null;
  preview: string | null;
  thumbnail: string | null;
  downloadable: boolean;
}

function text(value: unknown): string | null {
  return typeof value === 'string' ? value : null;
}

/** The file in a field value; any other value (a string, a number, null) is not one. */
export function asFile(value: unknown): FileData | null {
  if (typeof value !== 'object' || value === null) {
    return null;
  }

  const file = value as Record<string, unknown>;

  if (typeof file.path !== 'string' || typeof file.name !== 'string') {
    return null;
  }

  return {
    path: file.path,
    name: file.name,
    size: typeof file.size === 'number' ? file.size : null,
    preview: text(file.preview),
    thumbnail: text(file.thumbnail),
    downloadable: file.downloadable !== false,
  };
}

/** The download address the server put on the cell, only for a file that may be downloaded. */
export function downloadHref(field: FieldSchema & { download?: unknown }, file: FileData): string | null {
  return file.downloadable ? text(field.download) : null;
}

/** What shows in place of an image that is missing or did not load. */
export function ImagePlaceholder({ size, rounded }: { size: number; rounded: boolean }) {
  return (
    <span
      data-placeholder=""
      aria-hidden="true"
      style={{ width: size, height: size }}
      className={`inline-flex shrink-0 items-center justify-center bg-muted text-muted-foreground ${rounded ? 'rounded-full' : 'rounded-md'}`}
    >
      <ImageOff className="size-1/2" />
    </span>
  );
}

/**
 * The form control of a file field: the current file with a remove control, and the chunked
 * uploader below it; `media` draws the file itself.
 */
export function FileControl({
  field,
  value,
  onChange,
  id,
  describedBy,
  disabled,
  media,
}: Pick<FormFieldProps, 'field' | 'value' | 'onChange' | 'id' | 'describedBy' | 'disabled'> & {
  media: (file: FileData) => React.ReactNode;
}) {
  const { __ } = useTranslation();
  const file = asFile(value);

  return (
    <div role="group" aria-describedby={describedBy} className="flex flex-col gap-2">
      {file === null ? (
        <span className="text-sm text-muted-foreground">{__('No file')}</span>
      ) : (
        <div className="flex flex-wrap items-center gap-3">
          {media(file)}
          {field.deletable !== false && !disabled && (
            <button
              type="button"
              className="text-sm text-destructive underline-offset-2 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
              onClick={() => {
                onChange(null);
              }}
            >
              {__('Remove')}
            </button>
          )}
        </div>
      )}
      <div data-upload-slot="">{disabled ? null : <Uploader field={field} attribute={field.attribute} onChange={onChange} id={id} describedBy={describedBy} />}</div>
    </div>
  );
}
