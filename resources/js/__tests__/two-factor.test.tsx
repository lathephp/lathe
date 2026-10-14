import { router } from '@inertiajs/react';
import { fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

const svg = '<svg xmlns="http://www.w3.org/2000/svg" data-testid="qr"><rect width="1" height="1"/></svg>';
const oldCodes = ['aaaaaaaaaa-1111111111', 'bbbbbbbbbb-2222222222', 'cccccccccc-3333333333', 'dddddddddd-4444444444', 'eeeeeeeeee-5555555555', 'ffffffffff-6666666666', 'gggggggggg-7777777777', 'hhhhhhhhhh-8888888888'];
const newCodes = oldCodes.map((code) => code.replace(/^./, 'z'));

/** fetch answers by method and URL; anything else is a 500. */
function respond(answers: Record<string, [number, unknown]>) {
  return vi.spyOn(globalThis, 'fetch').mockImplementation((input, init) => {
    const [status, body] = answers[`${init?.method ?? 'GET'} ${String(input)}`] ?? [500, null];

    return Promise.resolve(new Response(body === '' ? '' : JSON.stringify(body), { status }));
  });
}

function sent(fetch: ReturnType<typeof respond>): string[] {
  return fetch.mock.calls.map(([url, init]) => `${init?.method ?? 'GET'} ${String(url)}${typeof init?.body === 'string' ? ` ${init.body}` : ''}`);
}

function renderSecurity(twoFactor: 'off' | 'pending' | 'on') {
  return renderPage('Security', { features: { updatePasswords: false, twoFactorAuthentication: true }, twoFactor }, { url: '/lathe/security' });
}

describe('two-factor card', () => {
  it('Off: Enable posts to the enable route, then reloads the state', async () => {
    const fetch = respond({ 'POST /lathe/user/two-factor-authentication': [200, ''] });
    const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    renderSecurity('off');

    fireEvent.click(screen.getByRole('button', { name: 'Enable' }));

    await waitFor(() => {
      expect(reload).toHaveBeenCalledWith(expect.objectContaining({ only: ['twoFactor'] }));
    });
    expect(sent(fetch)).toEqual(['POST /lathe/user/two-factor-authentication']);
  });

  it('Pending: shows the QR code and the secret left to right, and confirms with the 6-digit code', async () => {
    const fetch = respond({
      'GET /lathe/user/two-factor-qr-code': [200, { svg, url: 'otpauth://totp/x' }],
      'GET /lathe/user/two-factor-secret-key': [200, { secretKey: 'JBSWY3DPEHPK3PXP' }],
      'POST /lathe/user/confirmed-two-factor-authentication': [200, ''],
    });
    const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    renderSecurity('pending');

    const secret = await screen.findByText('JBSWY3DPEHPK3PXP');
    const qr = await screen.findByTestId('qr');
    expect(secret.closest('[dir="ltr"]')).not.toBeNull();
    expect(qr.closest('[dir="ltr"]')).not.toBeNull();

    fireEvent.change(screen.getByRole('textbox', { name: 'Code' }), { target: { value: '123456' } });
    fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));

    await waitFor(() => {
      expect(reload).toHaveBeenCalledWith(expect.objectContaining({ only: ['twoFactor'] }));
    });
    expect(sent(fetch)).toContain('POST /lathe/user/confirmed-two-factor-authentication {"code":"123456"}');
  });

  it('Pending: shows a wrong code under the input and stays Pending', async () => {
    respond({
      'GET /lathe/user/two-factor-qr-code': [200, { svg, url: 'otpauth://totp/x' }],
      'GET /lathe/user/two-factor-secret-key': [200, { secretKey: 'JBSWY3DPEHPK3PXP' }],
      'POST /lathe/user/confirmed-two-factor-authentication': [422, { message: 'x', errors: { code: ['The provided two factor authentication code was invalid.'] } }],
    });
    const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    renderSecurity('pending');

    fireEvent.change(screen.getByRole('textbox', { name: 'Code' }), { target: { value: '000000' } });
    fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));

    const error = await screen.findByText('The provided two factor authentication code was invalid.');
    expect(screen.getByRole('textbox', { name: 'Code' }).getAttribute('aria-describedby')).toBe(error.id);
    expect(reload).not.toHaveBeenCalled();
  });

  it('On: shows, regenerates and disables', async () => {
    const fetch = respond({
      'GET /lathe/user/two-factor-recovery-codes': [200, oldCodes],
      'POST /lathe/user/two-factor-recovery-codes': [200, newCodes],
      'DELETE /lathe/user/two-factor-authentication': [200, ''],
    });
    const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    renderSecurity('on');

    fireEvent.click(screen.getByRole('button', { name: 'Show recovery codes' }));
    expect(await screen.findByText('aaaaaaaaaa-1111111111')).toBeTruthy();
    expect(screen.getAllByRole('listitem')).toHaveLength(8);

    fireEvent.click(screen.getByRole('button', { name: 'Regenerate recovery codes' }));
    expect(await screen.findByText('zaaaaaaaaa-1111111111')).toBeTruthy();
    expect(screen.queryByText('aaaaaaaaaa-1111111111')).toBeNull();

    fireEvent.click(screen.getByRole('button', { name: 'Disable' }));
    await waitFor(() => {
      expect(reload).toHaveBeenCalledWith(expect.objectContaining({ only: ['twoFactor'] }));
    });
    expect(sent(fetch)).toEqual([
      'GET /lathe/user/two-factor-recovery-codes',
      'POST /lathe/user/two-factor-recovery-codes',
      'DELETE /lathe/user/two-factor-authentication',
    ]);
  });

  it('shows no two-factor card without the feature', () => {
    renderPage('Security', { features: { updatePasswords: true, twoFactorAuthentication: false }, twoFactor: 'off' }, { url: '/lathe/security' });

    expect(screen.queryByRole('button', { name: 'Enable' })).toBeNull();
  });
});

describe('two-factor challenge page', () => {
  function renderChallenge(errors: Record<string, string> = {}) {
    return renderPage('Auth/TwoFactorChallenge', { errors }, { url: '/lathe/two-factor-challenge' });
  }

  it('posts the 6-digit code', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderChallenge();

    fireEvent.change(screen.getByRole('textbox', { name: 'Code' }), { target: { value: '123456' } });
    fireEvent.click(screen.getByRole('button', { name: 'Sign in' }));

    expect(visit).toHaveBeenCalledOnce();
    expect(visit.mock.calls[0]?.[0]).toBe('/lathe/two-factor-challenge');
    expect(visit.mock.calls[0]?.[1]).toMatchObject({ method: 'post', data: { code: '123456' } });
  });

  it('toggles to a recovery code and posts that instead', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderChallenge();

    fireEvent.click(screen.getByRole('button', { name: 'Use a recovery code' }));
    expect(screen.queryByRole('textbox', { name: 'Code' })).toBeNull();

    fireEvent.change(screen.getByLabelText('Recovery code'), { target: { value: 'aaaaaaaaaa-1111111111' } });
    fireEvent.click(screen.getByRole('button', { name: 'Sign in' }));

    expect(visit.mock.calls[0]?.[1]).toMatchObject({ method: 'post', data: { recovery_code: 'aaaaaaaaaa-1111111111' } });
    expect(visit.mock.calls[0]?.[1]).not.toMatchObject({ data: { code: expect.anything() as unknown } });

    fireEvent.click(screen.getByRole('button', { name: 'Use an authentication code' }));
    expect(screen.getByRole('textbox', { name: 'Code' })).toBeTruthy();
  });

  it('shows a wrong code under the input, left to right', () => {
    renderChallenge({ code: 'The provided two factor authentication code was invalid.' });

    const code = screen.getByRole('textbox', { name: 'Code' });
    const error = screen.getByText('The provided two factor authentication code was invalid.');

    expect(code.getAttribute('aria-describedby')).toBe(error.id);
    expect(code.closest('[dir="ltr"]')).not.toBeNull();
  });
});
