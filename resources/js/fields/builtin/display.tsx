import { BadgeIndex, IconDetail, IconIndex, LineIndex, SparklineDetail, SparklineIndex, StackIndex, StatusIndex } from '../display/DisplayFields';
import { FormFallbackField, registerField } from '../registry';

/** Register the renderers of the badge, status, icon, sparkline, stack and line fields. */
export function registerDisplayFields(): void {
  // A badge and a status hold a plain value, so their form is a text input.
  registerField('badge-field', { index: BadgeIndex, form: FormFallbackField });
  registerField('status-field', { index: StatusIndex, form: FormFallbackField });
  registerField('icon-field', { index: IconIndex, detail: IconDetail });
  registerField('sparkline-field', { index: SparklineIndex, detail: SparklineDetail });
  registerField('stack-field', { index: StackIndex });
  registerField('line-field', { index: LineIndex });
}
