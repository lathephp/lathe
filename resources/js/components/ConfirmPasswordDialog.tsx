import { ApiError, request, useLathe, useTranslation } from '@lathephp/core';
import { useEffect, useId, useRef, useState } from 'react';
import { FieldError } from './FieldError';
import { Modal, ModalClose, modalFooter, modalInput, outlineButton, primaryButton } from './Modal';

/** The confirm password form over the page: onDone(true) once confirmed, onDone(false) on Escape or Cancel. */
export function ConfirmPasswordDialog({ onDone }: { onDone: (confirmed: boolean) => void }) {
  const id = useId();

  return (
    <Modal
      labelledBy={`${id}-title`}
      onClose={() => {
        onDone(false);
      }}
    >
      <ConfirmPasswordForm titleId={`${id}-title`} onDone={onDone} />
    </Modal>
  );
}

/** The form alone, which an open modal shows in place of its content while a request waits on it. */
export function ConfirmPasswordForm({ titleId, onDone }: { titleId: string; onDone: (confirmed: boolean) => void }) {
  const { __ } = useTranslation();
  const { basePath } = useLathe();
  const id = useId();
  const input = useRef<HTMLInputElement>(null);
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | undefined>(undefined);
  const [busy, setBusy] = useState(false);

  async function confirm() {
    setBusy(true);
    setError(undefined);

    try {
      await request(`${basePath}/confirm-password`, { method: 'POST', body: { password } });
      onDone(true);
    } catch (failure) {
      const message = failure instanceof ApiError && failure.status === 422 ? (failure.data as { errors?: { password?: string[] } } | null)?.errors?.password?.[0] : undefined;

      setError(message ?? __('Something went wrong. Please try again.'));
      setBusy(false);
      input.current?.focus();
    }
  }

  useEffect(() => {
    input.current?.focus();
  }, []);

  return (
    <form
      noValidate
      // Its own column, so it keeps the dialog's spacing in place of any modal's content.
      className="flex flex-col gap-5"
      onSubmit={(event) => {
        event.preventDefault();
        void confirm();
      }}
    >
      <h2 id={titleId} className="-mb-3.5 text-base font-semibold">
        {__('Confirm your password')}
      </h2>
      <p className="text-sm text-muted-foreground">{__('For your security, please confirm your password to continue.')}</p>
      <div className="flex flex-col gap-1.5">
        <label htmlFor={`${id}-password`} className="text-sm font-medium">
          {__('Password')}
        </label>
        <input
          ref={input}
          id={`${id}-password`}
          type="password"
          autoComplete="current-password"
          value={password}
          aria-invalid={error !== undefined || undefined}
          aria-describedby={error === undefined ? undefined : `${id}-error`}
          onChange={(event) => {
            setPassword(event.target.value);
          }}
          className={modalInput}
        />
        <FieldError id={`${id}-error`} message={error} />
      </div>
      <div className={modalFooter}>
        <button
          type="button"
          className={outlineButton}
          onClick={() => {
            onDone(false);
          }}
        >
          {__('Cancel')}
        </button>
        <button type="submit" disabled={busy} className={primaryButton}>
          {__('Confirm')}
        </button>
      </div>
      <ModalClose
        onClick={() => {
          onDone(false);
        }}
      />
    </form>
  );
}
