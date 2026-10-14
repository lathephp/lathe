import { Suspense, type ComponentType } from 'react';
import { BelongsToDetail, BelongsToIndex } from '../relation/BelongsToField';
import { BelongsToForm, HasOneForm, MorphToForm, TagForm } from '../relation/lazy';
import { MorphToDetail, MorphToIndex } from '../relation/MorphToField';
import { TagDetail, TagIndex } from '../relation/TagField';
import { registerField, type FormFieldProps } from '../registry';


/** A lazy form input, holding an input's height while its chunk loads. */
function deferred(Form: ComponentType<FormFieldProps>): ComponentType<FormFieldProps> {
  return function DeferredForm(props: FormFieldProps) {
    return (
      <Suspense fallback={<div className="h-9" aria-hidden="true" />}>
        <Form {...props} />
      </Suspense>
    );
  };
}

/** Register the renderers of the relation fields. */
export function registerRelationFields(): void {
  registerField('belongs-to-field', { index: BelongsToIndex, detail: BelongsToDetail, form: deferred(BelongsToForm) });
  registerField('morph-to-field', { index: MorphToIndex, detail: MorphToDetail, form: deferred(MorphToForm) });
  registerField('tag-field', { index: TagIndex, detail: TagDetail, form: deferred(TagForm) });
  registerField('has-one-field', { form: deferred(HasOneForm) });
  registerField('morph-one-field', { form: deferred(HasOneForm) });
}
