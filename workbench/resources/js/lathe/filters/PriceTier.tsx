import { filter, type FilterProps } from '@lathephp/core';
import { Button, Label } from '@lathephp/ui';

/** The price bands as a row of buttons, the chosen one pressed. */
function PriceTier({ filter: definition, value, onChange }: FilterProps) {
  return (
    <div role="group" aria-label={definition.name} data-price-tier className="grid gap-1.5">
      <Label>{definition.name}</Label>
      <div className="flex gap-1">
        {(definition.options ?? []).map((option) => (
          <Button
            key={option.value}
            type="button"
            size="sm"
            variant={option.value === value ? 'default' : 'outline'}
            aria-pressed={option.value === value}
            onClick={() => {
              onChange(option.value);
            }}
          >
            {option.label}
          </Button>
        ))}
      </div>
    </div>
  );
}

filter('workbench-price-tier', PriceTier);
