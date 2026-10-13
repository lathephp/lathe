import { SafeHtml } from '../../components/SafeHtml';
import { registerField, stringValue, type DetailFieldProps, type FormFieldProps, type IndexFieldProps } from '../registry';
import { TextInput } from '../text/TextInput';
import { isSafeHtml, TextValue } from '../text/TextValue';

function TextIndex({ field, cell }: IndexFieldProps) {
  return <TextValue field={field} value={cell.value} display={cell.display} />;
}

function TextDetail({ field }: DetailFieldProps) {
  return <TextValue field={field} value={field.value} display={field.display} />;
}

function TextareaDetail({ field }: DetailFieldProps) {
  return <TextValue field={field} value={field.value} display={field.display} multiline />;
}

/** Only http, https and mailto addresses become links: a stored value never runs script. */
function linkTarget(value: unknown): string | null {
  try {
    const url = new URL(stringValue(value));

    return ['http:', 'https:', 'mailto:'].includes(url.protocol) ? url.href : null;
  } catch {
    return null;
  }
}

function UrlValue({ value, display }: { value: unknown; display: string }) {
  const href = linkTarget(value);

  return href === null ? (
    display
  ) : (
    <a href={href} target="_blank" rel="noopener noreferrer" className="text-primary underline-offset-2 hover:underline">
      {display}
    </a>
  );
}

function UrlIndex({ cell }: IndexFieldProps) {
  return <UrlValue value={cell.value} display={cell.display} />;
}

function UrlDetail({ field }: DetailFieldProps) {
  return <UrlValue value={field.value} display={field.display} />;
}

function ColorValue({ value, display }: { value: unknown; display: string }) {
  const color = stringValue(value);

  return /^#[0-9a-f]{3,8}$/i.test(color) ? (
    <span className="inline-flex items-center gap-2">
      <span aria-hidden="true" className="size-4 rounded border border-border" style={{ backgroundColor: color }} />
      {display}
    </span>
  ) : (
    display
  );
}

function ColorIndex({ cell }: IndexFieldProps) {
  return <ColorValue value={cell.value} display={cell.display} />;
}

function ColorDetail({ field }: DetailFieldProps) {
  return <ColorValue value={field.value} display={field.display} />;
}

/** A password never reaches the page, so the cell shows a mask. */
function PasswordMask() {
  return <span aria-hidden="true">••••••••</span>;
}

function IdIndex({ cell }: IndexFieldProps) {
  return stringValue(cell.value);
}

function Heading({ field }: DetailFieldProps | FormFieldProps) {
  const content = field.content;

  return isSafeHtml(content) ? <SafeHtml html={content} className="text-base font-semibold" /> : <p className="text-base font-semibold">{field.label}</p>;
}

function TextForm(props: FormFieldProps) {
  return <TextInput {...props} kind="text" />;
}

function TextareaForm(props: FormFieldProps) {
  return <TextInput {...props} kind="textarea" />;
}

function EmailForm(props: FormFieldProps) {
  return <TextInput {...props} kind="email" />;
}

function UrlForm(props: FormFieldProps) {
  return <TextInput {...props} kind="url" />;
}

function ColorForm(props: FormFieldProps) {
  return <TextInput {...props} kind="color" />;
}

function PasswordForm(props: FormFieldProps) {
  return <TextInput {...props} kind="password" />;
}

function HiddenForm({ field, value }: FormFieldProps) {
  return <input type="hidden" name={field.attribute} value={stringValue(value)} />;
}

/** Register the renderers of the text, textarea, email, url, color, password, hidden, heading and id fields. */
export function registerTextFields(): void {
  registerField('text-field', { index: TextIndex, detail: TextDetail, form: TextForm });
  registerField('textarea-field', { index: TextIndex, detail: TextareaDetail, form: TextareaForm });
  registerField('email-field', { index: TextIndex, form: EmailForm });
  registerField('url-field', { index: UrlIndex, detail: UrlDetail, form: UrlForm });
  registerField('color-field', { index: ColorIndex, detail: ColorDetail, form: ColorForm });
  registerField('password-field', { index: PasswordMask, detail: PasswordMask, form: PasswordForm });
  registerField('hidden-field', { form: HiddenForm });
  registerField('heading-field', { detail: Heading, form: Heading });
  registerField('id-field', { index: IdIndex });
}
