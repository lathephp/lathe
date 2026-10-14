import type { ComponentType } from 'react';
import type { FormFieldProps } from '../registry';
import { useEditorUploads } from './useEditorUploads';

export type ImageEditor = ComponentType<FormFieldProps & { uploadImage?: (file: File) => Promise<string> }>;

/** An editor of a field with files: it uploads images, and an upload the server refuses says why under it. */
export function EditorWithFiles({ editor: Editor, ...props }: FormFieldProps & { editor: ImageEditor }) {
  const { uploadImage, error } = useEditorUploads(props.field);

  return (
    <>
      <Editor {...props} uploadImage={uploadImage} />
      {error !== null && <p className="mt-1 text-sm text-destructive">{error}</p>}
    </>
  );
}
