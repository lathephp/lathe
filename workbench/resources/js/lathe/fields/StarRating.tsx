import { field, useTranslation, type DetailFieldProps, type FieldProps, type IndexFieldProps } from '@lathephp/core';
import { Button } from '@lathephp/ui';

const stars = [1, 2, 3, 4, 5];

function rating(value: unknown): number {
  return typeof value === 'number' ? value : 0;
}

/** Five stars with the first `value` filled, and the rating as text for screen readers. */
function Stars({ value }: { value: number }) {
  return (
    <span data-star-rating className="inline-flex items-center gap-0.5">
      {stars.map((star) => (
        <span key={star} aria-hidden="true" data-filled={star <= value || undefined} className={star <= value ? 'text-warning' : 'text-muted-foreground'}>
          ★
        </span>
      ))}
      <span className="sr-only">{`${String(value)} / 5`}</span>
    </span>
  );
}

function StarRatingForm({ field: definition, value, onChange, id, describedBy, invalid, disabled }: FieldProps) {
  const { __ } = useTranslation();
  const current = rating(value);

  return (
    <div id={id} role="group" aria-label={definition.label} aria-describedby={describedBy} aria-invalid={invalid || undefined} data-star-rating className="flex gap-1">
      {stars.map((star) => (
        <Button
          key={star}
          type="button"
          variant="ghost"
          size="icon-sm"
          disabled={disabled}
          aria-label={__(':count stars', { count: star })}
          aria-pressed={star === current}
          data-filled={star <= current || undefined}
          onClick={() => {
            onChange(star);
          }}
          className={`text-2xl leading-none ${star <= current ? 'text-warning' : 'text-muted-foreground'}`}
        >
          ★
        </Button>
      ))}
    </div>
  );
}

function StarRatingIndex({ cell }: IndexFieldProps) {
  return <Stars value={rating(cell.value)} />;
}

function StarRatingDetail({ field: shown }: DetailFieldProps) {
  return <Stars value={rating(shown.value)} />;
}

field('workbench-star-rating', { form: StarRatingForm, index: StarRatingIndex, detail: StarRatingDetail });
