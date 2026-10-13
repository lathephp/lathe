import { useTranslation } from '@lathephp/core';
import { stringValue, type DetailFieldProps, type FormFieldProps } from '../registry';

type Row = { key: string; value: string };

/** The rows a stored object or an edited row list holds; anything else holds none. */
function toRows(value: unknown): Row[] {
  if (Array.isArray(value)) {
    return value.map((item: unknown) => {
      const row = typeof item === 'object' && item !== null ? (item as Record<string, unknown>) : {};

      return { key: stringValue(row.key), value: stringValue(row.value) };
    });
  }

  return typeof value === 'object' && value !== null ? Object.entries(value).map(([key, item]) => ({ key, value: stringValue(item) })) : [];
}

function text(field: { [option: string]: unknown }, name: string, fallback: string): string {
  const given = field[name];

  return typeof given === 'string' ? given : fallback;
}

const control =
  'h-9 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60 aria-[invalid]:border-destructive';

function NoValue({ text: message }: { text: string }) {
  return <span className="text-muted-foreground">{message}</span>;
}

export function KeyValueDetail({ field }: DetailFieldProps) {
  const rows = toRows(field.value);

  if (rows.length === 0) {
    return <NoValue text={text(field, 'noValueText', '—')} />;
  }

  return (
    <table className="w-full text-sm">
      <thead>
        <tr className="text-muted-foreground">
          <th className="py-1 pe-4 text-start font-medium">{text(field, 'keyLabel', '')}</th>
          <th className="py-1 text-start font-medium">{text(field, 'valueLabel', '')}</th>
        </tr>
      </thead>
      <tbody>
        {rows.map((row, index) => (
          <tr key={index} className="border-t border-border">
            <td className="py-1 pe-4">{row.key}</td>
            <td className="py-1">{row.value}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

/** Sends the pairs as a list of key and value rows, so a repeated key reaches the server's check. */
export default function KeyValueForm({ field, value, onChange, id, describedBy, invalid, disabled }: FormFieldProps) {
  const { __ } = useTranslation();
  const rows = toRows(value);
  const keyLabel = text(field, 'keyLabel', __('Key'));
  const valueLabel = text(field, 'valueLabel', __('Value'));
  const lockKeys = field.disableEditingKeys === true;
  const edit = (index: number, change: Partial<Row>) => {
    onChange(rows.map((row, at) => (at === index ? { ...row, ...change } : row)));
  };

  return (
    <div id={id} aria-describedby={describedBy} className="space-y-2">
      {rows.length === 0 ? (
        <NoValue text={text(field, 'noValueText', __('No Data'))} />
      ) : (
        <table className="w-full text-sm">
          <thead>
            <tr>
              <th className="pb-1 pe-2 text-start font-medium">{keyLabel}</th>
              <th className="pb-1 pe-2 text-start font-medium">{valueLabel}</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {rows.map((row, index) => (
              <tr key={index}>
                <td className="pb-2 pe-2">
                  <input
                    type="text"
                    aria-label={`${keyLabel} ${String(index + 1)}`}
                    aria-invalid={invalid || undefined}
                    value={row.key}
                    disabled={disabled || lockKeys}
                    onChange={(event) => {
                      edit(index, { key: event.target.value });
                    }}
                    className={control}
                  />
                </td>
                <td className="pb-2 pe-2">
                  <input
                    type="text"
                    aria-label={`${valueLabel} ${String(index + 1)}`}
                    aria-invalid={invalid || undefined}
                    value={row.value}
                    disabled={disabled}
                    onChange={(event) => {
                      edit(index, { value: event.target.value });
                    }}
                    className={control}
                  />
                </td>
                <td className="pb-2">
                  {field.disableDeletingRows === true ? null : (
                    <button
                      type="button"
                      aria-label={__('Remove row :number', { number: index + 1 })}
                      disabled={disabled}
                      className="h-9 rounded-md border border-input px-3 text-sm disabled:opacity-60"
                      onClick={() => {
                        onChange(rows.filter((_, at) => at !== index));
                      }}
                    >
                      ×
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
      {field.disableAddingRows === true ? null : (
        <button
          type="button"
          disabled={disabled}
          className="h-9 rounded-md border border-input px-3 text-sm disabled:opacity-60"
          onClick={() => {
            onChange([...rows, { key: '', value: '' }]);
          }}
        >
          {text(field, 'actionText', __('Add row'))}
        </button>
      )}
    </div>
  );
}
