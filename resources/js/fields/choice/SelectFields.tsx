import { Badge, isBadgeTone, type BadgeTone } from '../../components/ui/Badge';
import { cn } from '../../lib/cn';
import type { FieldSchema } from '../types';
import { stringValue, type DetailFieldProps, type IndexFieldProps } from '../registry';
import { labelOf, optionsOf, stringsOf } from './options';

// A tone's label colour and its tick fill; a neutral label stays readable while its ticks are grey.
const meterTones: Record<BadgeTone, [string, string]> = {
  neutral: ['text-muted-foreground', 'bg-chart-other'],
  primary: ['text-primary', 'bg-primary'],
  info: ['text-info', 'bg-info'],
  success: ['text-success', 'bg-success'],
  warning: ['text-warning', 'bg-warning'],
  danger: ['text-destructive', 'bg-destructive'],
};

function meterOf(field: FieldSchema): unknown[] | null {
  return Array.isArray(field.meter) ? field.meter : null;
}

function styled(field: FieldSchema): boolean {
  return badgesOf(field) !== null || meterOf(field) !== null;
}

function shown(field: IndexFieldProps['field'], value: unknown): string {
  return field.displayUsingLabels === true || styled(field) ? labelOf(optionsOf(field), stringValue(value)) : stringValue(value);
}

export function badgesOf(field: FieldSchema): Record<string, unknown> | null {
  return typeof field.badges === 'object' && field.badges !== null ? (field.badges as Record<string, unknown>) : null;
}

/** A select's value as a badge in the tone badges() gave it, or as filled ticks when it has a meter; null when it has neither. */
export function SelectBadge({ field, value }: { field: FieldSchema; value: unknown }) {
  const badges = badgesOf(field);
  const meter = meterOf(field);
  const text = shown(field, value);

  if ((badges === null && meter === null) || text === '') {
    return null;
  }

  const raw = badges?.[stringValue(value)];
  const tone = isBadgeTone(raw) ? raw : 'neutral';

  if (meter !== null) {
    const filled = meter.map(stringValue).indexOf(stringValue(value)) + 1;

    return (
      <span data-lathe-meter="" data-lathe-meter-tone={tone} className={cn('inline-flex items-center gap-[7px] text-xs font-medium whitespace-nowrap', meterTones[tone][0])}>
        <span aria-hidden="true" className="inline-flex gap-0.5">
          {meter.map((_, index) => (
            <span key={index} data-lathe-meter-tick="" data-on={index < filled ? '' : undefined} className={cn('h-3 w-[5px] rounded-[2px]', meterTones[tone][1], index < filled ? '' : 'opacity-[0.22]')} />
          ))}
        </span>
        {text}
      </span>
    );
  }

  return (
    <Badge tone={tone} dot>
      {text}
    </Badge>
  );
}

export function SelectIndex({ field, cell }: IndexFieldProps) {
  return styled(field) ? <SelectBadge field={field} value={cell.value} /> : shown(field, cell.value);
}

export function SelectDetail({ field }: DetailFieldProps) {
  const text = shown(field, field.value);

  if (text === '') {
    return <span className="text-muted-foreground">—</span>;
  }

  return styled(field) ? <SelectBadge field={field} value={field.value} /> : text;
}

/** Tags for a list of values, each with the label of its option. */
export function Tags({ field, value }: { field: IndexFieldProps['field']; value: unknown }) {
  const options = optionsOf(field);

  return (
    <span className="inline-flex flex-wrap gap-1">
      {stringsOf(value).map((item) => (
        <span key={item} className="rounded-md bg-muted px-2 py-0.5 text-xs">
          {labelOf(options, item)}
        </span>
      ))}
    </span>
  );
}
