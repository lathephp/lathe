import { useState, type ComponentType } from 'react';
import type { FormFieldProps } from '../registry';
import { useAssociatable } from '../relation/useAssociatable';
import { stringsOf } from './options';
import type { RemoteSource } from './SelectFields';

/**
 * A select whose options the server searches: the first focus asks for them, a typed term
 * asks again after the debounce.
 */
export function RemoteChoice({ Form, ...props }: FormFieldProps & { Form: ComponentType<FormFieldProps & { remote?: RemoteSource }> }) {
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const { items } = useAssociatable(props.field, { open, term: query, withTrashed: false });
  const found = items.map((item) => ({ value: String(item.value), label: item.display }));
  // A saved value is its own label, so it shows with no lookup and the select keeps it.
  const kept = stringsOf(props.value)
    .filter((value) => value !== '' && !found.some((option) => option.value === value))
    .map((value) => ({ value, label: value }));

  return (
    <Form
      {...props}
      remote={{
        options: [...kept, ...found],
        query,
        onQuery: setQuery,
        onFocus: () => {
          setOpen(true);
        },
      }}
    />
  );
}
