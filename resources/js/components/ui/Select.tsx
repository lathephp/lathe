import { ChevronDown } from 'lucide-react';
import { Suspense, useState, type KeyboardEvent, type ReactNode } from 'react';
import { cn } from '../../lib/cn';
import { lazyNamed } from '../../lib/lazy';

export interface SelectOption {
  value: string;
  label: string;
  /** Options in a row with the same group show under its heading. */
  group?: string;
  /** Shown but not pickable, such as a column another row has taken. */
  disabled?: boolean;
}

interface CommonProps {
  options: readonly SelectOption[];
  id?: string;
  placeholder?: string;
  size?: 'default' | 'sm';
  disabled?: boolean;
  className?: string;
  'aria-label'?: string;
  'aria-labelledby'?: string;
  'aria-describedby'?: string;
  'aria-invalid'?: boolean;
  'aria-required'?: boolean;
  /** Called when the trigger takes the focus, such as to load remote options. */
  onFocus?: () => void;
  /** Draws a single select's chosen value in the trigger, such as a status badge, in place of its label. */
  display?: (value: string) => ReactNode;
}

export interface SingleSelectProps extends CommonProps {
  multiple?: false;
  value: string | null;
  onChange: (value: string) => void;
}

export interface MultiSelectProps extends CommonProps {
  multiple: true;
  value: readonly string[];
  onChange: (value: string[]) => void;
}

export type SelectProps = SingleSelectProps | MultiSelectProps;

/** What the trigger shows: the display of the chosen value, or its label, or the labels joined. */
export function triggerContent(props: SelectProps): ReactNode {
  if (props.multiple !== true && props.display !== undefined && props.value !== null && props.value !== '') {
    return props.display(props.value);
  }

  return selectedText(props) ?? props.placeholder;
}

/** What the trigger reads: the chosen option's label, or the chosen labels joined. */
export function selectedText(props: SelectProps): string | undefined {
  const labels = props.options.filter((option) => (props.multiple === true ? props.value.includes(option.value) : option.value === props.value)).map((option) => option.label);

  return labels.length === 0 ? undefined : labels.join(', ');
}

// The designed list carries Base UI, so it loads on the first press and first paint
// carries only this trigger, drawn with the same classes as the kit's.
const SelectMenu = lazyNamed(() => import('./SelectMenu').then((module) => module.SelectMenu));

let menuLoaded = false;

const openKeys = new Set(['Enter', ' ', 'ArrowDown', 'ArrowUp']);

export const triggerClass =
  'cn-select-trigger flex w-fit items-center justify-between whitespace-nowrap outline-none disabled:cursor-not-allowed disabled:opacity-50 *:data-[slot=select-value]:line-clamp-1 *:data-[slot=select-value]:flex *:data-[slot=select-value]:items-center [&_svg]:pointer-events-none [&_svg]:shrink-0';

/** A select of the panel: a designed list in place of the browser's own. */
export function Select(props: SelectProps) {
  const [opened, setOpened] = useState<boolean | null>(menuLoaded ? false : null);

  if (opened !== null) {
    return (
      <Suspense fallback={<SelectTrigger {...props} />}>
        <SelectMenu {...props} defaultOpen={opened} />
      </Suspense>
    );
  }

  const open = () => {
    void SelectMenu.preload().then(() => {
      menuLoaded = true;
    });
    setOpened(true);
  };

  return (
    <SelectTrigger
      {...props}
      onPreload={() => {
        void SelectMenu.preload();
      }}
      onOpen={open}
    />
  );
}

type TriggerProps = SelectProps & {
  onPreload?: () => void;
  onOpen?: () => void;
};

function SelectTrigger(props: TriggerProps) {
  // eslint-disable-next-line @typescript-eslint/no-unused-vars -- value, options, multiple, onChange and display are read through triggerContent
  const { value, options, multiple, onChange, display, id, placeholder, size = 'default', disabled, className, onPreload, onOpen, onFocus, ...aria } = props;
  const label = selectedText(props);
  const content = triggerContent(props);

  // The list is not in the page until it loads, so there is nothing for aria-controls to
  // name yet; the Base UI trigger that takes this one's place sets it.
  return (
    <button
      type="button"
      // eslint-disable-next-line jsx-a11y/role-has-required-aria-props
      role="combobox"
      aria-expanded="false"
      aria-haspopup="listbox"
      id={id}
      disabled={disabled}
      data-slot="select-trigger"
      data-size={size}
      className={cn(triggerClass, className)}
      onPointerEnter={onPreload}
      onFocus={() => {
        onPreload?.();
        onFocus?.();
      }}
      // As Base UI's trigger: a mouse opens on the press, touch and pen on the click.
      onMouseDown={(event) => {
        if (event.button === 0) {
          event.preventDefault();
          onOpen?.();
        }
      }}
      onClick={() => {
        onOpen?.();
      }}
      onKeyDown={(event: KeyboardEvent) => {
        if (openKeys.has(event.key)) {
          event.preventDefault();
          onOpen?.();
        }
      }}
      {...aria}
    >
      <span data-slot="select-value" {...(label === undefined ? { 'data-placeholder': '' } : {})}>
        {content}
      </span>
      <ChevronDown aria-hidden="true" className="cn-select-trigger-icon pointer-events-none" />
    </button>
  );
}
