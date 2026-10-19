import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ApiError, QueryClientProvider, queryClient, request, useQuery, useTranslation } from '@lathephp/core';
import { Button, Card, CardContent, CardDescription, CardHeader, CardTitle } from '@lathephp/ui';
import { useState, type SubmitEvent } from 'react';
import { FieldError } from '../components/FieldError';
import { TotpInput } from '../components/TotpInput';
import { authInputClass } from '../layout/AuthLayout';
import { PageHeader } from '../layout/PageHeader';
import type { SharedProps } from '../types';

type TwoFactorState = 'off' | 'pending' | 'on';

interface SecurityProps {
  features: { updatePasswords: boolean; twoFactorAuthentication: boolean };
  twoFactor: TwoFactorState;
}

type PasswordErrors = Partial<Record<'current_password' | 'password', string>>;

function PasswordField({ id, label, autoComplete, value, error, onChange }: { id: string; label: string; autoComplete: string; value: string; error?: string; onChange: (value: string) => void }) {
  return (
    <div className="space-y-1.5">
      <label htmlFor={id} className="text-sm font-medium">
        {label}
      </label>
      <input
        id={id}
        name={id}
        type="password"
        autoComplete={autoComplete}
        value={value}
        aria-invalid={error === undefined ? undefined : true}
        aria-describedby={error === undefined ? undefined : `${id}-error`}
        className={authInputClass}
        onChange={(event) => {
          onChange(event.target.value);
        }}
      />
      <FieldError id={`${id}-error`} message={error} />
    </div>
  );
}

function PasswordCard() {
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
  const form = useForm({ current_password: '', password: '', password_confirmation: '' });
  const errors: PasswordErrors = form.errors;

  function submit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    form.put(`${basePath}/user-security/password`, {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
      },
      onError: () => {
        form.reset('password', 'password_confirmation');
      },
    });
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{__('Password')}</CardTitle>
        <CardDescription>{__('Use a long, random password to keep your account safe.')}</CardDescription>
      </CardHeader>
      <CardContent>
        <form className="max-w-sm space-y-4" noValidate onSubmit={submit}>
          <PasswordField
            id="current_password"
            label={__('Current password')}
            autoComplete="current-password"
            value={form.data.current_password}
            error={errors.current_password}
            onChange={(value) => {
              form.setData('current_password', value);
            }}
          />
          <PasswordField
            id="password"
            label={__('New password')}
            autoComplete="new-password"
            value={form.data.password}
            error={errors.password}
            onChange={(value) => {
              form.setData('password', value);
            }}
          />
          <PasswordField
            id="password_confirmation"
            label={__('Confirm password')}
            autoComplete="new-password"
            value={form.data.password_confirmation}
            onChange={(value) => {
              form.setData('password_confirmation', value);
            }}
          />
          <Button type="submit" disabled={form.processing}>
            {__('Update password')}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}

/** The server decides the card's state, so a pending setup survives a reload. */
function reloadTwoFactor() {
  router.reload({ only: ['twoFactor'] });
}

/** A 423 the user cancelled, or a failure the client already reported: the card stays as it is. */
function ignore() {
  return undefined;
}

function PendingTwoFactor() {
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
  const [code, setCode] = useState('');
  const [error, setError] = useState<string | undefined>(undefined);
  const [busy, setBusy] = useState(false);
  // gcTime 0: the secret is not kept once the card moves on.
  const setup = useQuery({
    queryKey: ['two-factor-setup'],
    queryFn: async ({ signal }) => {
      const [qr, secret] = await Promise.all([
        request<{ svg: string }>(`${basePath}/user/two-factor-qr-code`, { signal }),
        request<{ secretKey: string }>(`${basePath}/user/two-factor-secret-key`, { signal }),
      ]);

      return { svg: qr?.svg ?? '', secretKey: secret?.secretKey ?? '' };
    },
    gcTime: 0,
    staleTime: Infinity,
  });

  function confirm(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    request(`${basePath}/user/confirmed-two-factor-authentication`, { method: 'POST', body: { code } })
      .then(reloadTwoFactor, (e: unknown) => {
        const errors = e instanceof ApiError && e.status === 422 ? (e.data as { errors?: { code?: string[] } } | null)?.errors : undefined;
        setError(errors?.code?.[0]);
      })
      .finally(() => {
        setBusy(false);
      });
  }

  return (
    <div className="space-y-4">
      <p className="text-sm text-muted-foreground">{__('Scan the QR code with your authenticator app, or enter the setup key, then enter the 6-digit code it shows.')}</p>
      {setup.data !== undefined && (
        <div className="space-y-2" dir="ltr">
          {/* Fortify draws this SVG from the user's own secret; it carries no user input. */}
          <div className="inline-block rounded-md bg-white p-2" dangerouslySetInnerHTML={{ __html: setup.data.svg }} />
          <p className="font-mono text-sm break-all">{setup.data.secretKey}</p>
        </div>
      )}
      <form className="max-w-sm space-y-4" noValidate onSubmit={confirm}>
        <div className="space-y-1.5">
          <label htmlFor="two-factor-code" className="text-sm font-medium">
            {__('Code')}
          </label>
          <TotpInput id="two-factor-code" value={code} error={error} onChange={setCode} />
          <FieldError id="two-factor-code-error" message={error} />
        </div>
        <Button type="submit" disabled={busy}>
          {__('Confirm')}
        </Button>
      </form>
    </div>
  );
}

function EnabledTwoFactor() {
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
  const [codes, setCodes] = useState<string[] | null>(null);
  const [busy, setBusy] = useState(false);
  const url = `${basePath}/user/two-factor-recovery-codes`;

  function run(send: () => Promise<unknown>) {
    setBusy(true);
    send()
      .catch(ignore)
      .finally(() => {
        setBusy(false);
      });
  }

  return (
    <div className="space-y-4">
      <p className="text-sm text-muted-foreground">{__('Two-factor authentication is on. Keep your recovery codes somewhere safe; each one signs you in once.')}</p>
      {codes !== null && (
        <ul dir="ltr" aria-label={__('Recovery codes')} className="grid gap-1 rounded-md bg-muted p-3 font-mono text-sm sm:grid-cols-2">
          {codes.map((code) => (
            <li key={code}>{code}</li>
          ))}
        </ul>
      )}
      <div className="flex flex-wrap gap-2">
        {codes === null ? (
          <Button
            variant="outline"
            disabled={busy}
            onClick={() => {
              run(async () => {
                setCodes((await request<string[]>(url)) ?? []);
              });
            }}
          >
            {__('Show recovery codes')}
          </Button>
        ) : (
          <Button
            variant="outline"
            disabled={busy}
            onClick={() => {
              run(async () => {
                setCodes((await request<string[]>(url, { method: 'POST' })) ?? []);
              });
            }}
          >
            {__('Regenerate recovery codes')}
          </Button>
        )}
        <Button
          variant="outline"
          // The tinted destructive variant falls under 4.5:1; the panel marks a destructive button with red text instead.
          className="text-destructive"
          disabled={busy}
          onClick={() => {
            run(async () => {
              await request(`${basePath}/user/two-factor-authentication`, { method: 'DELETE' });
              reloadTwoFactor();
            });
          }}
        >
          {__('Disable')}
        </Button>
      </div>
    </div>
  );
}

function TwoFactorCard({ state }: { state: TwoFactorState }) {
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
  const [busy, setBusy] = useState(false);

  return (
    <Card>
      <CardHeader>
        <CardTitle>{__('Two-factor authentication')}</CardTitle>
        <CardDescription>{__('Ask for a code from an authenticator app each time you sign in.')}</CardDescription>
      </CardHeader>
      <CardContent>
        {state === 'pending' && (
          // The page reads through the shared client even where no provider wraps it.
          <QueryClientProvider client={queryClient}>
            <PendingTwoFactor />
          </QueryClientProvider>
        )}
        {state === 'on' && <EnabledTwoFactor />}
        {state === 'off' && (
          <Button
            disabled={busy}
            onClick={() => {
              setBusy(true);
              request(`${basePath}/user/two-factor-authentication`, { method: 'POST' })
                .then(reloadTwoFactor, ignore)
                .finally(() => {
                  setBusy(false);
                });
            }}
          >
            {__('Enable')}
          </Button>
        )}
      </CardContent>
    </Card>
  );
}

/** Password and two-factor settings for the signed-in user. */
export default function Security({ features, twoFactor }: SecurityProps) {
  const { __ } = useTranslation();

  return (
    <div className="mx-auto w-full max-w-3xl space-y-6 p-6">
      <Head title={__('Security')} />
      <PageHeader title={__('Security')} />
      {features.updatePasswords && <PasswordCard />}
      {features.twoFactorAuthentication && <TwoFactorCard state={twoFactor} />}
    </div>
  );
}
