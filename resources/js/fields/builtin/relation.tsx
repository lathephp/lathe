import { BelongsToDetail, BelongsToIndex } from '../relation/BelongsToField';
import { BelongsToForm } from '../relation/BelongsToForm';
import { registerField } from '../registry';

/** Register the renderers of the relation fields. */
export function registerRelationFields(): void {
  registerField('belongs-to-field', { index: BelongsToIndex, detail: BelongsToDetail, form: BelongsToForm });
}
