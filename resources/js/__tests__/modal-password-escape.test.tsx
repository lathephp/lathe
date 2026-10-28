import { __panel } from '@lathephp/core';
import { act, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { Modal } from '../components/Modal';

/** The password check the open modal registered, taken and put back. */
function modalConfirmer(): () => Promise<boolean> {
  const confirmer = __panel.setPasswordConfirmer(() => Promise.resolve(false));
  __panel.setPasswordConfirmer(confirmer);

  return confirmer as () => Promise<boolean>;
}

/** Resolves once the password form is on the page, having pressed Escape in the same moment. */
function escapeAsTheFormAppears(): Promise<void> {
  return new Promise((resolve) => {
    const observer = new MutationObserver(() => {
      if (document.body.textContent.includes('Confirm your password')) {
        observer.disconnect();
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        resolve();
      }
    });
    observer.observe(document.body, { childList: true, subtree: true, characterData: true });
  });
}

describe('a password check inside a modal', () => {
  it('goes back to the modal on an Escape pressed as soon as the check shows', async () => {
    const onClose = vi.fn();
    render(
      <Modal labelledBy="title" onClose={onClose}>
        <h2 id="title">Mark 1 orders as shipped?</h2>
        <button type="button">Mark shipped</button>
      </Modal>,
    );

    // The first check loads the form's chunk; cancel it and ask again with the chunk in hand.
    const first = modalConfirmer()();
    expect(await screen.findByText('Confirm your password')).toBeTruthy();
    await act(async () => {
      document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
      await first;
    });

    const escaped = escapeAsTheFormAppears();
    const second = modalConfirmer()();
    await escaped;
    await act(async () => {
      await Promise.resolve();
    });

    expect(onClose).not.toHaveBeenCalled();
    expect(await second).toBe(false);
    expect(await screen.findByRole('dialog', { name: 'Mark 1 orders as shipped?' })).toBeTruthy();
  });
});
