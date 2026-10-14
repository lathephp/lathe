import { BelongsToDetail, BelongsToIndex } from '../relation/BelongsToField';
import { BelongsToForm } from '../relation/BelongsToForm';
import { MorphToDetail, MorphToIndex } from '../relation/MorphToField';
import { MorphToForm } from '../relation/MorphToForm';
import { TagDetail, TagForm, TagIndex } from '../relation/TagField';
import { registerField } from '../registry';

/** Register the renderers of the relation fields. */
export function registerRelationFields(): void {
  registerField('belongs-to-field', { index: BelongsToIndex, detail: BelongsToDetail, form: BelongsToForm });
  registerField('morph-to-field', { index: MorphToIndex, detail: MorphToDetail, form: MorphToForm });
  registerField('tag-field', { index: TagIndex, detail: TagDetail, form: TagForm });
}
