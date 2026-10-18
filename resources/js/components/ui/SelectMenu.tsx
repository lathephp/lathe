import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@lathephp/ui/select';
import { selectedText, type SelectOption, type SelectProps } from './Select';

/** The options cut into runs of one group each; a run without a group has none. */
function runs(options: readonly SelectOption[]): { group: string | undefined; options: SelectOption[] }[] {
  const found: { group: string | undefined; options: SelectOption[] }[] = [];

  for (const option of options) {
    const last = found.at(-1);

    if (last !== undefined && last.group === option.group) {
      last.options.push(option);
    } else {
      found.push({ group: option.group, options: [option] });
    }
  }

  return found;
}

/** The Base UI select behind the panel's Select, loaded on the first press. */
export function SelectMenu(props: SelectProps & { defaultOpen: boolean }) {
  // eslint-disable-next-line @typescript-eslint/no-unused-vars -- value, multiple and onChange go to the root below
  const { options, id, placeholder, size = 'default', disabled = false, className, defaultOpen, value, multiple, onChange, onFocus, ...aria } = props;
  const parts = (
    <>
      <SelectTrigger id={id} size={size} className={className} onFocus={onFocus} {...aria}>
        <SelectValue placeholder={placeholder}>{() => selectedText(props) ?? placeholder}</SelectValue>
      </SelectTrigger>
      <SelectContent alignItemWithTrigger={false} align="start">
        {runs(options).map((run) => {
          const items = run.options.map((option) => (
            <SelectItem key={option.value} value={option.value}>
              {option.label}
            </SelectItem>
          ));

          return run.group === undefined ? (
            items
          ) : (
            <SelectGroup key={`group:${run.group}`}>
              <SelectLabel>{run.group}</SelectLabel>
              {items}
            </SelectGroup>
          );
        })}
      </SelectContent>
    </>
  );

  if (props.multiple === true) {
    const change = props.onChange;

    return (
      <Select
        multiple
        value={[...props.value]}
        items={options}
        defaultOpen={defaultOpen}
        disabled={disabled}
        onValueChange={(next) => {
          change(next.map(String));
        }}
      >
        {parts}
      </Select>
    );
  }

  const change = props.onChange;

  return (
    <Select
      value={props.value}
      items={options}
      defaultOpen={defaultOpen}
      disabled={disabled}
      onValueChange={(next) => {
        if (typeof next === 'string') {
          change(next);
        }
      }}
    >
      {parts}
    </Select>
  );
}
