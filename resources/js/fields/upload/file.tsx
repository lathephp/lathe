import { ImageOff } from 'lucide-react';
import { Suspense, type ComponentProps } from 'react';
import { lazyNamed } from '../../lib/lazy';
import type { FieldSchema } from '../types';

// The chunked uploader loads with the first form that has a file field, not with the index.
export const Uploader = lazyNamed(() => import('./Uploader').then((module) => module.Uploader));

// The file control loads with a form too; the cells and details need only asFile and the placeholder.
export const LazyFileControl = lazyNamed(() => import('./FileControl').then((module) => module.FileControl));

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

/** The form control of a file field, holding an input's height while its chunk loads. */
export function FileControl(props: ComponentProps<typeof LazyFileControl>) {
  return (
    <Suspense fallback={<div className="h-9" aria-hidden="true" />}>
      <LazyFileControl {...props} />
    </Suspense>
  );
}
