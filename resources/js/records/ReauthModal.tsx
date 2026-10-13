import { ApiError, request, useLathe, useTranslation } from '@lathephp/core';
import { useId, useState } from 'react';
import { FieldError } from '../components/FieldError';
import { Modal } from '../components/Modal';
import { firstErrors } from './useRecordForm';

interface ReauthModalProps {
  email: string;
  /** Signed in again as the same user: replay the request that failed. */
  onSignedIn: () => void;
  /** Signed in as someone else: the form is not theirs to submit. */
  onOtherUser: () => void;
  onClose: () => void;
}

/** The session ended under the form: sign in again without leaving it. */
export function ReauthModal({ email: initialEmail, onSignedIn, onOtherUser, onClose }: ReauthModalProps) {
  const { __ } = useTranslation();
  const { basePath } = useLathe();
  const id = useId();
  const [email, setEmail] = useState(initialEmail);
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | undefined>(undefined);
  const [busy, setBusy] = useState(false);

  async function signIn() {
    setBusy(true);
    setError(undefined);

    try {
      await request(`${basePath}/login`, { method: 'POST', body: { email, password } });

      if (email.trim().toLowerCase() === initialEmail.trim().toLowerCase()) {
        onSignedIn();
      } else {
        onOtherUser();
      }
    } catch (failure) {
      setError(failure instanceof ApiError && failure.status === 422 ? (Object.values(firstErrors(failure.data))[0] ?? __('These credentials do not match our records.')) : __('Something went wrong. Please try again.'));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal labelledBy={`${id}-title`} onClose={onClose}>
      <form
        noValidate
        className="space-y-4"
        onSubmit={(event) => {
          event.preventDefault();
          void signIn();
        }}
      >
        <h2 id={`${id}-title`} className="text-base font-semibold">
          {__('Your session has expired')}
        </h2>
        <p className="text-sm text-muted-foreground">{__('Sign in again to save your changes.')}</p>
        <div className="space-y-1">
          <label htmlFor={`${id}-email`} className="text-sm font-medium">
            {__('Email')}
          </label>
          <input
            id={`${id}-email`}
            type="email"
            autoComplete="username"
            value={email}
            onChange={(event) => {
              setEmail(event.target.value);
            }}
            className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
          />
        </div>
        <div className="space-y-1">
          <label htmlFor={`${id}-password`} className="text-sm font-medium">
            {__('Password')}
          </label>
          <input
            id={`${id}-password`}
            type="password"
            autoComplete="current-password"
            value={password}
            aria-invalid={error !== undefined || undefined}
            aria-describedby={error === undefined ? undefined : `${id}-error`}
            onChange={(event) => {
              setPassword(event.target.value);
            }}
            className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
          />
          <FieldError id={`${id}-error`} message={error} />
        </div>
        <div className="flex justify-end gap-2">
          <button type="button" className="h-9 rounded-md px-4 text-sm" onClick={onClose}>
            {__('Cancel')}
          </button>
          <button type="submit" disabled={busy} className="h-9 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-60">
            {__('Sign in')}
          </button>
        </div>
      </form>
    </Modal>
  );
}
