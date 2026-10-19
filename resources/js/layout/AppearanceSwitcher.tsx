import { useTheme, useTranslation, type Appearance } from '@lathephp/core';
import { Monitor, Moon, Sun } from 'lucide-react';

const choices: [Appearance, typeof Sun][] = [
  ['light', Sun],
  ['dark', Moon],
  ['system', Monitor],
];

/** Light, Dark and System as a segmented control inside the user menu. */
export function AppearanceSwitcher() {
  const { __ } = useTranslation();
  const { appearance, setAppearance } = useTheme();
  const labels: Record<Appearance, string> = { light: __('Light'), dark: __('Dark'), system: __('System') };

  return (
    <div role="group" aria-label={__('Appearance')} className="lathe-appearance">
      <span aria-hidden="true">{__('Appearance')}</span>
      <div>
        {choices.map(([choice, Icon]) => (
          <button
            key={choice}
            type="button"
            role="menuitemradio"
            aria-checked={appearance === choice}
            onClick={() => {
              setAppearance(choice);
            }}
          >
            <Icon aria-hidden="true" className="size-3.5" />
            {labels[choice]}
          </button>
        ))}
      </div>
    </div>
  );
}
