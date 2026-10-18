import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@lathephp/ui/select';
import { selectedText, type SelectProps } from './Select';

/** The Base UI select behind the panel's Select, loaded on the first press. */
export function SelectMenu(props: SelectProps & { defaultOpen: boolean }) {
  // eslint-disable-next-line @typescript-eslint/no-unused-vars -- value, multiple and onChange go to the root below
  const { options, id, placeholder, size = 'default', disabled = false, className, defaultOpen, value, multiple, onChange, ...aria } = props;
  const parts = (
    <>
      <SelectTrigger id={id} size={size} className={className} {...aria}>
        <SelectValue placeholder={placeholder}>{() => selectedText(props) ?? placeholder}</SelectValue>
      </SelectTrigger>
      <SelectContent alignItemWithTrigger={false} align="start">
        {options.map((option) => (
          <SelectItem key={option.value} value={option.value}>
            {option.label}
          </SelectItem>
        ))}
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
