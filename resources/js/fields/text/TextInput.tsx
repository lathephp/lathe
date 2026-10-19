import type { FormFieldProps } from '../registry';
import { stringValue } from '../registry';
import { CopyButton } from './CopyButton';
import { stringList } from './TextValue';

export type InputKind = 'text' | 'email' | 'url' | 'password' | 'color' | 'textarea';

interface ControlProps extends Omit<FormFieldProps, 'value' | 'onChange'> {
  kind: InputKind;
  value: string;
  onChange: (value: string) => void;
  label?: string;
}

function Control({ kind, field, value, onChange, id, describedBy, invalid, disabled, label }: ControlProps) {
  const limit = typeof field.maxlength === 'number' ? field.maxlength : undefined;
  const enforced = field.enforceMaxlength === true && limit !== undefined;
  const suggestions = kind === 'text' ? stringList(field.suggestions) : [];
  const listId = suggestions.length > 0 ? `${id}-suggestions` : undefined;
  const shared = {
    id,
    name: field.attribute,
    'aria-label': label,
    'aria-describedby': describedBy,
    'aria-invalid': invalid || undefined,
    'aria-required': field.required === true || undefined,
    disabled,
    placeholder: field.placeholder ?? undefined,
    maxLength: enforced ? limit : undefined,
  };
  // The browser blocks typing past maxlength; the slice covers a paste or a script.
  const edit = (next: string) => {
    onChange(enforced ? next.slice(0, limit) : next);
  };

  return (
    <>
      {kind === 'textarea' ? (
        <textarea
          {...shared}
          rows={typeof field.rows === 'number' ? field.rows : 4}
          value={value}
          onChange={(event) => {
            edit(event.target.value);
          }}
          className="lathe-input h-auto py-2"
        />
      ) : (
        <input
          {...shared}
          type={kind}
          list={listId}
          autoComplete={field.autocomplete ?? (kind === 'password' ? 'new-password' : undefined)}
          value={kind === 'color' && value === '' ? '#000000' : value}
          onChange={(event) => {
            edit(event.target.value);
          }}
          className={kind === 'color' ? 'lathe-input w-16 cursor-pointer p-1' : 'lathe-input'}
        />
      )}
      {listId !== undefined && (
        <datalist id={listId}>
          {suggestions.map((suggestion) => (
            <option key={suggestion} value={suggestion} />
          ))}
        </datalist>
      )}
    </>
  );
}

/** A text-like form input; a translatable field shows one input per locale. */
export function TextInput({ kind, ...props }: FormFieldProps & { kind: InputKind }) {
  const { field, value, onChange } = props;
  const locales = stringList(field.translatable);
  const copy = field.copyable === true;

  if (locales.length > 0) {
    const values: Record<string, unknown> = typeof value === 'object' && value !== null ? (value as Record<string, unknown>) : {};

    return (
      <div className="space-y-2">
        {locales.map((locale, index) => (
          <div key={locale} className="flex items-center gap-2">
            <span className="w-8 shrink-0 text-xs font-medium text-muted-foreground uppercase">{locale}</span>
            <Control
              {...props}
              kind={kind}
              id={index === 0 ? props.id : `${props.id}-${locale}`}
              label={`${field.label} (${locale})`}
              value={stringValue(values[locale])}
              onChange={(next) => {
                onChange({ ...values, [locale]: next });
              }}
            />
          </div>
        ))}
      </div>
    );
  }

  return (
    <div className="flex items-center gap-2">
      <Control
        {...props}
        kind={kind}
        value={stringValue(value)}
        onChange={(next) => {
          onChange(next);
        }}
      />
      {copy && <CopyButton text={stringValue(value)} />}
    </div>
  );
}
