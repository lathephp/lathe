import { router } from '@inertiajs/react';
import { request } from '@lathephp/core';
import { forgetUser } from '../lib/session';

/**
 * Starts (POST with the target) or stops (DELETE) an impersonation, then follows the server's
 * redirect. A redirect outside the panel is not an Inertia page, so it gets a full load. The user
 * changes either way, so what the browser keeps for the one leaving goes first.
 */
export async function impersonate(basePath: string, body?: { resource: string; resourceId: string | number }) {
  const reply = await request<{ redirect: string }>(`${basePath}/api/impersonate`, { method: body === undefined ? 'DELETE' : 'POST', body });
  forgetUser();
  const redirect = reply?.redirect ?? basePath;
  const url = new URL(redirect, location.href);

  if (url.origin === location.origin && (url.pathname === basePath || url.pathname.startsWith(`${basePath}/`))) {
    router.visit(redirect);
  } else {
    location.assign(redirect);
  }
}
