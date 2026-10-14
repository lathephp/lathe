import { filter, type FilterProps } from '@lathephp/core';

/** The price bands as a row of buttons, the chosen one pressed. */
function PriceTier({ filter: definition, value, onChange }: FilterProps) {
  return (
    <div role="group" aria-label={definition.name} data-price-tier className="grid gap-1.5">
      <span className="text-sm font-medium">{definition.name}</span>
      <div className="flex gap-1">
        {(definition.options ?? []).map((option) => (
          <button
            key={option.value}
            type="button"
            aria-pressed={option.value === value}
            onClick={() => {
              onChange(option.value);
            }}
            className="h-9 rounded-md border border-input px-3 text-sm aria-pressed:bg-amber-400 aria-pressed:text-black"
          >
            {option.label}
          </button>
        ))}
      </div>
    </div>
  );
}

filter('workbench-price-tier', PriceTier);
