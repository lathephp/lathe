import { useState } from 'react';
import { cn } from '../../lib/cn';
import type { DetailFieldProps, FormFieldProps, IndexFieldProps } from '../registry';
import type { FieldSchema } from '../types';
import { asFile, FileControl, ImagePlaceholder, type FileData } from './file';

function width(value: unknown, fallback: number): number {
  return typeof value === 'number' && value > 0 ? value : fallback;
}

function Picture({ field, file, size, source }: { field: FieldSchema; file: FileData; size: number; source: string | null }) {
  const [failed, setFailed] = useState(false);
  const rounded = field.rounded === true;

  if (source === null || failed) {
    return <ImagePlaceholder size={size} rounded={rounded} />;
  }

  return (
    <img
      src={source}
      alt={file.name}
      loading="lazy"
      style={{ width: size, aspectRatio: typeof field.aspect === 'string' ? field.aspect : '1 / 1' }}
      className={cn('shrink-0 object-cover', rounded ? 'rounded-full' : field.squared === true ? 'rounded-none' : 'rounded-md')}
      onError={() => {
        setFailed(true);
      }}
    />
  );
}

export function ImageIndex({ field, cell }: IndexFieldProps) {
  const file = asFile(cell.value);

  if (file === null) {
    return cell.display === '' ? <ImagePlaceholder size={width(field.indexWidth, 32)} rounded={field.rounded === true} /> : cell.display;
  }

  return <Picture field={field} file={file} size={width(field.indexWidth, 32)} source={file.thumbnail ?? file.preview} />;
}

export function ImageDetail({ field }: DetailFieldProps) {
  const file = asFile(field.value);

  if (file === null) {
    return field.display === '' ? <span className="text-muted-foreground">—</span> : field.display;
  }

  return <Picture field={field} file={file} size={width(field.detailWidth, 128)} source={file.preview ?? file.thumbnail} />;
}

export function ImageForm(props: FormFieldProps) {
  return <FileControl {...props} media={(file) => <Picture field={props.field} file={file} size={width(props.field.detailWidth, 128)} source={file.preview ?? file.thumbnail} />} />;
}
