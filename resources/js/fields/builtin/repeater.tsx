import { registerField } from '../registry';
import { RepeaterDetail, RepeaterForm } from '../repeater/RepeaterFields';

/** Register the renderers of the repeater fields. */
export function registerRepeaterFields(): void {
  registerField('repeater-field', { detail: RepeaterDetail, form: RepeaterForm });
}
