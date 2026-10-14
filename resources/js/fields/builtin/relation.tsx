import { BelongsToDetail, BelongsToIndex } from '../relation/BelongsToField';
import { registerField } from '../registry';

/** Register the renderers of the relation fields. */
export function registerRelationFields(): void {
  registerField('belongs-to-field', { index: BelongsToIndex, detail: BelongsToDetail });
}
