import { Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import type { SharedProps } from '../types';

interface ErrorProps {
  status: 403 | 404 | 419 | 500 | 503;
  message: string | null;
  shell: boolean;
}

/** The panel layout wraps this page when `shell` is true; otherwise it fills the screen. */
export default function ErrorPage({ status, message, shell }: ErrorProps) {
  const { __ } = useTranslation();
  const { basePath, auth } = usePage<SharedProps>().props;

  const copy = {
    403: [__('Forbidden'), __('You do not have permission to open this page.')],
    404: [__('Page not found'), __('The page you are looking for does not exist.')],
    419: [__('Your session expired'), __('Reload the page and try again.')],
    500: [__('Server error'), __('Something went wrong on the server.')],
    503: [__('Service unavailable'), __('The panel is down for maintenance. Try again soon.')],
  }[status];

  const content = (
    <div className="mx-auto max-w-md space-y-4 py-16 text-center">
      <p className="text-sm font-medium text-muted-foreground">{status}</p>
      <h1 className="text-2xl font-semibold">{copy[0]}</h1>
      <p className="text-sm text-muted-foreground">{message ?? copy[1]}</p>
      <div className="flex flex-wrap justify-center gap-2">
        {status === 419 && (
          <Button
            onClick={() => {
              location.reload();
            }}
          >
            {__('Reload')}
          </Button>
        )}
        {status === 403 && auth.user !== null && (
          <Button
            variant="outline"
            onClick={() => {
              router.post(`${basePath}/logout`);
            }}
          >
            {__('Sign out')}
          </Button>
        )}
        <Link
          href={basePath}
          className="inline-flex h-9 items-center rounded-md px-4 text-sm font-medium outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
        >
          {__('Back to dashboard')}
        </Link>
      </div>
    </div>
  );

  return shell ? content : <main className="min-h-svh bg-background p-6 text-foreground">{content}</main>;
}
