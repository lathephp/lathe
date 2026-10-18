import { Suspense } from 'react';
import { registerField, type DetailFieldProps } from '../registry';
import { RepeaterDetail, RepeaterForm } from '../repeater/lazy';
import { deferred } from './deferred';

function DeferredRepeaterDetail(props: DetailFieldProps) {
  return (
    <Suspense fallback={null}>
      <RepeaterDetail {...props} />
    </Suspense>
  );
}

/** Register the renderers of the repeater fields. */
export function registerRepeaterFields(): void {
  registerField('repeater-field', { detail: DeferredRepeaterDetail, form: deferred(RepeaterForm) });
}
