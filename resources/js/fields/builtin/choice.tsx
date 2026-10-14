import { BooleanDetail, BooleanForm, BooleanGroupDetail, BooleanGroupForm, BooleanGroupIndex, BooleanIndex } from '../choice/BooleanFields';
import { MultiSelectForm, SelectForm } from '../choice/lazy';
import { MultiSelectDetail, MultiSelectIndex } from '../choice/MultiSelectFields';
import { SelectDetail, SelectIndex } from '../choice/SelectFields';
import { registerField } from '../registry';
import { deferred } from './deferred';

/** Register the renderers of the boolean, select, booleanGroup, multiSelect, country and timezone fields. */
export function registerChoiceFields(): void {
  registerField('select-field', { index: SelectIndex, detail: SelectDetail, form: deferred(SelectForm) });
  registerField('boolean-field', { index: BooleanIndex, detail: BooleanDetail, form: BooleanForm });
  registerField('boolean-group-field', { index: BooleanGroupIndex, detail: BooleanGroupDetail, form: BooleanGroupForm });
  registerField('multi-select-field', { index: MultiSelectIndex, detail: MultiSelectDetail, form: deferred(MultiSelectForm) });
}
