import { BelongsToDetail, BelongsToIndex } from '../relation/BelongsToField';
import { BelongsToForm, HasOneForm, MorphToForm, TagForm } from '../relation/lazy';
import { MorphToDetail, MorphToIndex } from '../relation/MorphToField';
import { TagDetail, TagIndex } from '../relation/TagField';
import { registerField } from '../registry';
import { deferred } from './deferred';


/** Register the renderers of the relation fields. */
export function registerRelationFields(): void {
  registerField('belongs-to-field', { index: BelongsToIndex, detail: BelongsToDetail, form: deferred(BelongsToForm) });
  registerField('morph-to-field', { index: MorphToIndex, detail: MorphToDetail, form: deferred(MorphToForm) });
  registerField('tag-field', { index: TagIndex, detail: TagDetail, form: deferred(TagForm) });
  registerField('has-one-field', { form: deferred(HasOneForm) });
  registerField('morph-one-field', { form: deferred(HasOneForm) });
}
