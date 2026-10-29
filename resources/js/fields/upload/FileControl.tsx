import { useTranslation } from '@lathephp/core';
import { Suspense, useContext } from 'react';
import type { FormFieldProps } from '../registry';
import { RepeaterPathContext } from '../repeater/errors';
import { asFile, Uploader, type FileData } from './file';

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
  invalid,
  disabled,
  media,
}: Pick<FormFieldProps, 'field' | 'value' | 'onChange' | 'id' | 'describedBy' | 'invalid' | 'disabled'> & {
  media: (file: FileData) => React.ReactNode;
}) {
  const { __ } = useTranslation();
  // Inside a repeater row the upload names its scoped key, such as items.0.photo.
  const row = useContext(RepeaterPathContext);
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
      <div data-upload-slot="">
        <Suspense fallback={<div className="h-9" aria-hidden="true" />}>
          <Uploader field={field} attribute={row === '' ? field.attribute : `${row}.${field.attribute}`} onChange={onChange} id={id} describedBy={describedBy} invalid={invalid} disabled={disabled} />
        </Suspense>
      </div>
    </div>
  );
}
