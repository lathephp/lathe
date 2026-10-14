import { Suspense, type ComponentType } from 'react';
import type { FormFieldProps } from '../registry';

/** A lazy form input, holding an input's height while its chunk loads. */
export function deferred(Form: ComponentType<FormFieldProps>): ComponentType<FormFieldProps> {
  return function DeferredForm(props: FormFieldProps) {
    return (
      <Suspense fallback={<div className="h-9" aria-hidden="true" />}>
        <Form {...props} />
      </Suspense>
    );
  };
}
