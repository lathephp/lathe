import type { IndexProps } from './types';

export function IndexToolbar({ resource }: Pick<IndexProps, 'resource'>) {
  return <h1 className="text-2xl font-semibold">{resource.label}</h1>;
}
