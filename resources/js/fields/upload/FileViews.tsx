import { useTranslation } from '@lathephp/core';
import type { DetailFieldProps, FormFieldProps, IndexFieldProps } from '../registry';
import { asFile, downloadHref, FileControl } from './file';

export function FileIndex({ cell }: IndexFieldProps) {
  const file = asFile(cell.value);

  return file === null ? cell.display : file.name;
}

export function FileDetail({ field }: DetailFieldProps) {
  const { __ } = useTranslation();
  const file = asFile(field.value);

  if (file === null) {
    return field.display === '' ? <span className="text-muted-foreground">—</span> : field.display;
  }

  const href = downloadHref(field, file);

  return href === null ? (
    file.name
  ) : (
    <a href={href} download className="text-primary underline-offset-2 hover:underline" aria-label={`${__('Download')} ${file.name}`}>
      {file.name}
    </a>
  );
}

export function FileForm(props: FormFieldProps) {
  return <FileControl {...props} media={(file) => <span className="text-sm">{file.name}</span>} />;
}

export function AudioPlayer({ src, preload, label }: { src: string; preload: unknown; label: string }) {
  const strategy = preload === 'metadata' || preload === 'none' ? preload : 'auto';

  // A stored recording has no caption track to offer.
  // eslint-disable-next-line jsx-a11y/media-has-caption
  return <audio controls preload={strategy} src={src} aria-label={label} className="max-w-full" />;
}

export function AudioDetail({ field }: DetailFieldProps) {
  const file = asFile(field.value);

  if (file === null) {
    return field.display === '' ? <span className="text-muted-foreground">—</span> : field.display;
  }

  return file.preview === null ? file.name : <AudioPlayer src={file.preview} preload={field.preload} label={file.name} />;
}

export function AudioForm(props: FormFieldProps) {
  return (
    <FileControl
      {...props}
      media={(file) => (file.preview === null ? <span className="text-sm">{file.name}</span> : <AudioPlayer src={file.preview} preload={props.field.preload} label={file.name} />)}
    />
  );
}
