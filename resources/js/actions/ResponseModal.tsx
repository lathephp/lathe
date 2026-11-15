import { __panel, toast, useTranslation } from '@lathephp/core';
import { createElement, Suspense, useEffect, useId } from 'react';
import { Modal, ModalTitle } from '../components/Modal';

interface ResponseModalProps {
  /** The accessible name: the human title of the action that opened it, not the component's id. */
  title: string;
  component: string;
  data: Record<string, unknown>;
  onClose: () => void;
}

/** The modal an action's modal(component, data) result opens: the component registered with actionModal(). */
export function ResponseModal({ title, component, data, onClose }: ResponseModalProps) {
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
      <ModalTitle id={id} className="sr-only">
        {title}
      </ModalTitle>
      <Suspense fallback={null}>
        {createElement(registered, { data, close: onClose })}
      </Suspense>
    </Modal>
  );
}
