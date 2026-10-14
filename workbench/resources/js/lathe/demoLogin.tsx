import { config, renderHook } from '@lathephp/core';

interface DemoLogin {
  email: string;
  password: string;
}

// The live demo's server hands over its sign-in only when LATHE_DEMO is true; elsewhere this draws nothing.
renderHook('auth:after-form', function DemoLoginHint() {
  const login = config('demoLogin') as DemoLogin | undefined;

  if (login === undefined) {
    return null;
  }

  return (
    <p data-demo-login className="text-center text-sm text-muted-foreground">
      Sign in as <strong className="text-foreground">{login.email}</strong> with the password{' '}
      <strong className="text-foreground">{login.password}</strong>.
    </p>
  );
});
