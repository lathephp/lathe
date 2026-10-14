import { __panel, toast, useTranslation } from '@lathephp/core';
import { createElement, Suspense, useEffect, useId } from 'react';
import { Modal } from '../components/Modal';

interface ResponseModalProps {
  component: string;
  data: Record<string, unknown>;
  onClose: () => void;
}

/** The modal an action's modal(component, data) result opens: the component registered with actionModal(). */
export function ResponseModal({ component, data, onClose }: ResponseModalProps) {
  const { __ } = useTranslation();
  const id = useId();
  const registered = __panel.actionModalComponent(component);

  useEffect(() => {
    if (registered === undefined) {
      toast.error(__('Unknown modal component'));
      onClose();
    }
  }, [registered, __, onClose]);

  if (registered === undefined) {
    return null;
  }

  return (
    <Modal labelledBy={id} onClose={onClose}>
      <h2 id={id} className="sr-only">
        {component}
      </h2>
      <Suspense fallback={null}>
        {createElement(registered, { data, close: onClose })}
      </Suspense>
    </Modal>
  );
}
