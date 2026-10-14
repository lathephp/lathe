import { registerChoiceFields } from './choice';
import { registerDateFields } from './date';
import { registerDisplayFields } from './display';
import { registerNumberFields } from './number';
import { registerRelationFields } from './relation';
import { registerRepeaterFields } from './repeater';
import { registerRichFields } from './rich';
import { registerTextFields } from './text';
import { registerUploadFields } from './upload';

/** Register every built-in field renderer; a plugin may replace an entry afterwards. */
export function registerBuiltinFields(): void {
  registerTextFields();
  registerNumberFields();
  registerDateFields();
  registerChoiceFields();
  registerDisplayFields();
  registerRichFields();
  registerUploadFields();
  registerRepeaterFields();
  registerRelationFields();
}
