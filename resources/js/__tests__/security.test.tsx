import { router } from '@inertiajs/react';
import { fireEvent, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

describe('security page', () => {
  it('changes the password with the current one', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderPage('Security', { features: { updatePasswords: true, twoFactorAuthentication: false } }, { url: '/lathe/security' });

    expect(screen.getByRole('heading', { level: 1, name: 'Security' })).toBeTruthy();
    fireEvent.change(screen.getByLabelText('Current password'), { target: { value: 'password' } });
    fireEvent.change(screen.getByLabelText('New password'), { target: { value: 'new-password-2026' } });
    fireEvent.change(screen.getByLabelText('Confirm password'), { target: { value: 'new-password-2026' } });
    fireEvent.click(screen.getByRole('button', { name: 'Update password' }));

    expect(visit).toHaveBeenCalledWith(
      '/lathe/user-security/password',
      expect.objectContaining({ method: 'put', data: { current_password: 'password', password: 'new-password-2026', password_confirmation: 'new-password-2026' } }),
    );
  });

  it('shows no password card without the feature', () => {
    renderPage('Security', { features: { updatePasswords: false, twoFactorAuthentication: true } }, { url: '/lathe/security' });

    expect(screen.queryByLabelText('Current password')).toBeNull();
  });
});
