import { BooleanDetail, BooleanForm, BooleanGroupDetail, BooleanGroupForm, BooleanGroupIndex, BooleanIndex } from '../choice/BooleanFields';
import { MultiSelectDetail, MultiSelectForm, MultiSelectIndex } from '../choice/MultiSelectFields';
import { SelectDetail, SelectForm, SelectIndex } from '../choice/SelectFields';
import { registerField } from '../registry';

/** Register the renderers of the boolean, select, booleanGroup, multiSelect, country and timezone fields. */
export function registerChoiceFields(): void {
  registerField('select-field', { index: SelectIndex, detail: SelectDetail, form: SelectForm });
  registerField('boolean-field', { index: BooleanIndex, detail: BooleanDetail, form: BooleanForm });
  registerField('boolean-group-field', { index: BooleanGroupIndex, detail: BooleanGroupDetail, form: BooleanGroupForm });
  registerField('multi-select-field', { index: MultiSelectIndex, detail: MultiSelectDetail, form: MultiSelectForm });
}
