import { useTheme, useTranslation, type Appearance } from '@lathephp/core';
import { Check } from 'lucide-react';

const choices: Appearance[] = ['light', 'dark', 'system'];

/** Light, Dark and System as radio rows inside the user menu. */
export function AppearanceSwitcher() {
  const { __ } = useTranslation();
  const { appearance, setAppearance } = useTheme();
  const labels: Record<Appearance, string> = { light: __('Light'), dark: __('Dark'), system: __('System') };

  return (
    <div role="group" aria-label={__('Appearance')} className="border-y border-border py-1">
      {choices.map((choice) => (
        <button
          key={choice}
          type="button"
          role="menuitemradio"
          aria-checked={appearance === choice}
          className="flex h-8 w-full items-center gap-2 rounded-md px-2 text-sm outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
          onClick={() => {
            setAppearance(choice);
          }}
        >
          <span className="w-full text-start">{labels[choice]}</span>
          {appearance === choice && <Check aria-hidden="true" className="size-4" />}
        </button>
      ))}
    </div>
  );
}
