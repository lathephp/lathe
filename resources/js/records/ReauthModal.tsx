import { ApiError, request, useLathe, useTranslation } from '@lathephp/core';
import { Suspense, useId, useState } from 'react';
import { FieldError } from '../components/FieldError';
import { Modal, modalFooter, modalInput, outlineButton, primaryButton } from '../components/Modal';
import { lazyNamed } from '../lib/lazy';
import { firstErrors } from './useRecordForm';

// The OTP field loads only when an account asks for its code, which keeps it out of every page's first load.
const TotpInput = lazyNamed(() => import('../components/TotpInput').then((module) => module.TotpInput));

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
  // An account with confirmed two-factor answers the password with {"two_factor": true}; the code step follows.
  const [codeStep, setCodeStep] = useState(false);
  const [recovery, setRecovery] = useState(false);
  const [code, setCode] = useState('');
  const [recoveryCode, setRecoveryCode] = useState('');
  const [error, setError] = useState<string | undefined>(undefined);
  const [busy, setBusy] = useState(false);

  async function signIn() {
    setBusy(true);
    setError(undefined);

    try {
      if (codeStep) {
        await request(`${basePath}/two-factor-challenge`, { method: 'POST', body: recovery ? { recovery_code: recoveryCode } : { code } });
      } else {
        const answer = await request<{ two_factor?: boolean }>(`${basePath}/login`, { method: 'POST', body: { email, password } });

        if (answer?.two_factor === true) {
          setCodeStep(true);

          return;
        }
      }

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
        className="contents"
        onSubmit={(event) => {
          event.preventDefault();
          void signIn();
        }}
      >
        <h2 id={`${id}-title`} className="text-base font-semibold">
          {__('Your session has expired')}
        </h2>
        {codeStep ? (
          <>
            <p className="text-sm text-muted-foreground">{recovery ? __('Enter one of your recovery codes.') : __('Enter the 6-digit code from your authenticator app.')}</p>
            <div className="flex flex-col gap-1.5">
              {recovery ? (
                <>
                  <label htmlFor={`${id}-recovery`} className="text-sm font-medium">
                    {__('Recovery code')}
                  </label>
                  <input
                    id={`${id}-recovery`}
                    type="text"
                    dir="ltr"
                    autoComplete="one-time-code"
                    value={recoveryCode}
                    aria-invalid={error !== undefined || undefined}
                    aria-describedby={error === undefined ? undefined : `${id}-code-error`}
                    onChange={(event) => {
                      setRecoveryCode(event.target.value);
                    }}
                    className={modalInput}
                  />
                </>
              ) : (
                <>
                  <label htmlFor={`${id}-code`} className="text-sm font-medium">
                    {__('Code')}
                  </label>
                  <Suspense fallback={null}>
                    <TotpInput id={`${id}-code`} value={code} error={error} onChange={setCode} />
                  </Suspense>
                </>
              )}
              <FieldError id={`${id}-code-error`} message={error} />
            </div>
            <button
              type="button"
              className="self-start text-sm font-medium underline-offset-4 hover:underline"
              onClick={() => {
                setRecovery(!recovery);
              }}
            >
              {recovery ? __('Use an authentication code') : __('Use a recovery code')}
            </button>
          </>
        ) : (
          <>
            <p className="text-sm text-muted-foreground">{__('Sign in again to save your changes.')}</p>
            <div className="flex flex-col gap-1.5">
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
                className={modalInput}
              />
            </div>
            <div className="flex flex-col gap-1.5">
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
                className={modalInput}
              />
              <FieldError id={`${id}-error`} message={error} />
            </div>
          </>
        )}
        <div className={modalFooter}>
          <button type="button" className={outlineButton} onClick={onClose}>
            {__('Cancel')}
          </button>
          <button type="submit" disabled={busy} className={primaryButton}>
            {__('Sign in')}
          </button>
        </div>
      </form>
    </Modal>
  );
}
