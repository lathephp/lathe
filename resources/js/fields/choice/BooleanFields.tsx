import { useTranslation } from '@lathephp/core';
import { Check, X } from 'lucide-react';
import { stringValue, type DetailFieldProps, type FormFieldProps, type IndexFieldProps } from '../registry';
import { Checkbox } from '../../components/ui/Checkbox';
import { Switch } from '../../components/ui/Switch';
import { groupState, optionsOf } from './options';

/** The next value of a boolean: a plain one flips, a nullable one goes null, true, false, null. */
function next(value: unknown, nullable: boolean): boolean | null {
  if (!nullable) {
    return value !== true;
  }

  if (value === true) {
    return false;
  }

  return value === false ? null : true;
}

export function BooleanForm({ field, value, onChange, id, describedBy, invalid, disabled }: FormFieldProps) {
  const checked = value === true;
  const unset = field.nullable && (value === null || value === undefined);
  const flip = () => {
    onChange(next(value, field.nullable));
  };
  const props = { id, checked, disabled, onCheckedChange: flip, 'aria-describedby': describedBy, 'aria-invalid': invalid || undefined };

  return field.asCheckbox === true ? <Checkbox {...props} indeterminate={unset} /> : <Switch {...props} data-unset={unset || undefined} className={unset ? 'opacity-60' : undefined} />;
}

function Mark({ on, iconOnly = false }: { on: boolean; iconOnly?: boolean }) {
  const { __ } = useTranslation();
  const Icon = on ? Check : X;
  const text = on ? __('Yes') : __('No');

  return (
    <span className="inline-flex items-center gap-1">
      <Icon aria-hidden={!iconOnly} aria-label={iconOnly ? text : undefined} className="size-4" />
      {!iconOnly && text}
    </span>
  );
}

function BooleanValue({ value }: { value: unknown }) {
  if (value === true || value === false) {
    return <Mark on={value} />;
  }

  return stringValue(value);
}

export function BooleanIndex({ cell }: IndexFieldProps) {
  return <BooleanValue value={cell.value} />;
}

export function BooleanDetail({ field }: DetailFieldProps) {
  return field.value === null || field.value === undefined ? <span className="text-muted-foreground">—</span> : <BooleanValue value={field.value} />;
}

export function BooleanGroupForm({ field, value, onChange, id, describedBy, invalid, disabled }: FormFieldProps) {
  const options = optionsOf(field);
  const state = groupState(value);

  return (
    <fieldset id={id} aria-describedby={describedBy} aria-invalid={invalid || undefined} disabled={disabled} className="space-y-1">
      {options.map((option) => (
        <label key={option.value} className="flex items-center gap-2 text-sm">
          <Checkbox
            checked={state[option.value] === true}
            onCheckedChange={(checked) => {
              onChange(Object.fromEntries(options.map((item) => [item.value, item.value === option.value ? checked : state[item.value] === true])));
            }}
          />
          {option.label}
        </label>
      ))}
    </fieldset>
  );
}

/** The options a group display keeps: hideFalseValues drops the off ones, hideTrueValues the on ones. */
function GroupValue({ field, value }: { field: IndexFieldProps['field']; value: unknown }) {
  const state = groupState(value);
  const kept = optionsOf(field).filter((option) => {
    if (!(option.value in state)) {
      return false;
    }

    return state[option.value] ? field.hideTrueValues !== true : field.hideFalseValues !== true;
  });

  if (kept.length === 0) {
    return <span className="text-muted-foreground">{typeof field.noValueText === 'string' ? field.noValueText : ''}</span>;
  }

  return (
    <ul className="space-y-0.5">
      {kept.map((option) => (
        <li key={option.value}>
          <Mark on={state[option.value] === true} iconOnly /> {option.label}
        </li>
      ))}
    </ul>
  );
}

export function BooleanGroupIndex({ field, cell }: IndexFieldProps) {
  return <GroupValue field={field} value={cell.value} />;
}

export function BooleanGroupDetail({ field }: DetailFieldProps) {
  return <GroupValue field={field} value={field.value} />;
}
