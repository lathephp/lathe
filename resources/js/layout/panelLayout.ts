import { AppLayout } from './AppLayout';

/** Pages render inside the shell, except sign-in pages and errors the server marks as full screen. */
export function panelLayout(name: string, page: { props: Record<string, unknown> }): typeof AppLayout | null {
  if (name.startsWith('Auth/') || (name === 'Error' && page.props.shell !== true)) {
    return null;
  }

  return AppLayout;
}
