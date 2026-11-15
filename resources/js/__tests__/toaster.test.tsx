import { __panel, toast } from '@lathephp/core';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { Toaster } from '../components/Toaster';

afterEach(() => {
  vi.useRealTimers();
});

async function advance(ms: number) {
  await act(async () => {
    await vi.advanceTimersByTimeAsync(ms);
  });
}

describe('Toaster', () => {
  it('shows a success toast as a status', async () => {
    render(<Toaster />);

    act(() => {
      toast.success('Saved');
    });

    expect((await screen.findByRole('status')).textContent).toContain('Saved');
  });

  it('marks each toast with its kind by a 16 px outline icon, red only for an error, and a running task with a spinner', async () => {
    render(<Toaster />);

    act(() => {
      toast.success('Saved');
      toast.error('Not saved');
    });

    const saved = (await screen.findByText('Saved')).closest('[data-slot="toast"]') as HTMLElement;
    const failed = screen.getByRole('alert');
    expect(saved.querySelector('[data-toast-icon]')?.getAttribute('data-icon')).toBe('circle-check');
    expect(saved.querySelector('[data-toast-icon]')?.getAttribute('class')).toBe('size-4 shrink-0');
    expect(failed.querySelector('[data-toast-icon]')?.getAttribute('data-icon')).toBe('octagon-x');
    expect(failed.querySelector('[data-toast-icon]')?.getAttribute('class')).toBe('size-4 shrink-0 text-destructive');
    // The kind shows in the icon, so an error's text keeps the foreground colour.
    expect(failed.className).not.toContain('text-destructive');

    act(() => {
      __panel.putToast({ type: 'info', message: 'Marking 3 orders shipped', timeout: 0, dismissible: false });
    });
    const running = (await screen.findByText('Marking 3 orders shipped')).closest('[data-slot="toast"]') as HTMLElement;
    expect(running.querySelector('[data-toast-icon]')?.getAttribute('data-icon')).toBe('loader-circle');
    expect(running.querySelector('[data-toast-icon]')?.getAttribute('class')).toContain('animate-spin');
  });

  it("draws the style's toast card with the message as its title and the body under it", async () => {
    render(<Toaster />);

    act(() => {
      __panel.putToast({ type: 'success', message: 'Featured changed to Yes.', body: 'Product 12', action: { label: 'Undo', onClick: () => undefined }, timeout: 5000 });
    });

    const card = (await screen.findByText('Featured changed to Yes.')).closest('[data-slot="toast"]') as HTMLElement;
    // The style's cn-toast sets the corners, so the card names no radius of its own.
    expect(card.className).toContain('cn-toast');
    expect(card.className).not.toMatch(/(^|\s)rounded-/);
    expect(card.querySelector('[data-slot="toast-content"]')?.className).toContain('p-4');
    expect(card.querySelector('[data-slot="toast-title"]')?.textContent).toBe('Featured changed to Yes.');
    expect(card.querySelector('[data-slot="toast-description"]')?.textContent).toBe('Product 12');
    expect(within(card).getByRole('button', { name: 'Undo' }).className).toContain('cn-button-variant-outline');
    expect(within(card).getByRole('button', { name: 'Dismiss' }).className).toContain('cn-button-variant-ghost');
  });

  it('keeps an error until it is dismissed and closes a success after 5 seconds', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    render(<Toaster />);

    act(() => {
      toast.error('Not saved');
      toast.success('Saved');
    });

    const alert = await screen.findByRole('alert');
    expect(alert.textContent).toContain('Not saved');
    await advance(4_900);
    expect(screen.queryByText('Saved')).not.toBeNull();

    await advance(200);
    expect(screen.queryByText('Saved')).toBeNull();

    await advance(60_000);
    expect(screen.getByRole('alert').textContent).toContain('Not saved');

    fireEvent.click(within(screen.getByRole('alert')).getByRole('button', { name: 'Dismiss' }));
    await advance(0);
    expect(screen.queryByText('Not saved')).toBeNull();
  });

  it('keeps a running task on screen through a swipe and Escape, where a finished toast swipes away', async () => {
    render(<Toaster />);

    act(() => {
      __panel.putToast({ type: 'info', message: 'Marking 3 orders shipped', timeout: 0, dismissible: false });
      toast.success('Saved');
    });

    const swipe = async (text: string) => {
      const card = (await screen.findByText(text)).closest('[data-slot="toast"]') as HTMLElement;
      const pointer = { pointerId: 1, pointerType: 'mouse', button: 0, buttons: 1, isPrimary: true };

      await act(async () => {
        fireEvent.pointerDown(card, { ...pointer, clientX: 10, clientY: 10 });
        for (let step = 1; step <= 10; step++) {
          fireEvent.pointerMove(card, { ...pointer, clientX: 10 + step * 30, clientY: 10 });
        }
        fireEvent.pointerUp(card, { ...pointer, clientX: 310, clientY: 10, buttons: 0 });
        await new Promise((resolve) => setTimeout(resolve, 600));
      });
    };

    await swipe('Saved');
    expect(screen.queryByText('Saved')).toBeNull();

    await swipe('Marking 3 orders shipped');
    fireEvent.keyDown(screen.getByText('Marking 3 orders shipped'), { key: 'Escape' });
    await act(async () => {
      await new Promise((resolve) => setTimeout(resolve, 600));
    });

    expect(screen.getByText('Marking 3 orders shipped')).toBeTruthy();
    expect(__panel.toasts.get().map((item) => item.message)).toEqual(['Marking 3 orders shipped']);
  });

  it('keeps the newest three toasts in the stack and hides the older ones with no count', async () => {
    render(<Toaster />);

    act(() => {
      toast.success('One');
      toast.success('Two');
      toast.success('Three');
      toast.success('Four');
      toast.success('Five');
    });

    await screen.findByText('Five');
    const shown = (texts: string[]) =>
      screen
        .getAllByRole('status')
        .filter((element) => !element.hasAttribute('data-limited'))
        .map((element) => texts.find((text) => element.textContent.includes(text)))
        .sort();
    expect(shown(['One', 'Two', 'Three', 'Four', 'Five'])).toEqual(['Five', 'Four', 'Three']);
    expect(screen.getByText('One').closest('[data-limited]')).not.toBeNull();
    expect(screen.queryByText(/more$/)).toBeNull();
  });

  it('stacks the older toasts behind the newest one, which alone shows its content until the stack expands', async () => {
    render(<Toaster />);

    act(() => {
      toast.success('Older');
      toast.success('Newer');
    });

    const content = async (text: string) => (await screen.findByText(text)).closest('[data-slot="toast-content"]') as HTMLElement;
    const card = (await screen.findByText('Newer')).closest('[data-slot="toast"]') as HTMLElement;

    // Each card sits at the bottom of the viewport; Base UI's index and offset variables place it in the stack.
    expect(card.className).toMatch(/(^|\s)absolute(\s|$)/);
    expect(card.className).toContain('[--gap:0.75rem]');
    expect((await content('Older')).hasAttribute('data-behind')).toBe(true);
    expect((await content('Newer')).hasAttribute('data-behind')).toBe(false);
  });

  it('keeps a success toast past 5 seconds while the pointer is on the stack', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    render(<Toaster />);

    act(() => {
      toast.success('Saved');
    });

    await screen.findByText('Saved');
    fireEvent.mouseEnter(screen.getByRole('region', { name: 'Notifications' }));
    await advance(6_000);
    expect(screen.queryByText('Saved')).not.toBeNull();
  });

  it('closes a success toast after 5 seconds without hover', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    render(<Toaster />);

    act(() => {
      toast.success('Saved');
    });

    await screen.findByText('Saved');
    await advance(5_100);
    expect(screen.queryByText('Saved')).toBeNull();
  });

  it('keeps an error and a toast with duration 0 after a minute', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    render(<Toaster />);

    act(() => {
      toast.error('Not saved');
      toast.info('Exporting orders', { duration: 0 });
    });

    await screen.findByText('Exporting orders');
    await advance(60_000);
    expect(screen.queryByText('Not saved')).not.toBeNull();
    expect(screen.queryByText('Exporting orders')).not.toBeNull();
  });

  it('gives a success toast the status role and an error the alert role', async () => {
    render(<Toaster />);

    act(() => {
      toast.success('Saved');
      toast.error('Not saved');
    });

    expect((await screen.findByRole('status')).textContent).toContain('Saved');
    expect(screen.getByRole('alert').textContent).toContain('Not saved');
    expect(screen.getAllByRole('status')).toHaveLength(1);
    expect(screen.getAllByRole('alert')).toHaveLength(1);
  });

  it('runs an action and follows a link, and each dismisses the toast', async () => {
    const retry = vi.fn();
    render(<Toaster />);

    act(() => {
      toast.error('Export failed', { action: { label: 'Retry', onClick: retry } });
      toast.success('Export ready', { links: [{ label: 'Download', href: '/lathe/exports/1', newTab: true }] });
    });

    const link = await screen.findByRole('link', { name: 'Download' });
    expect(link.getAttribute('href')).toBe('/lathe/exports/1');
    expect(link.getAttribute('target')).toBe('_blank');
    link.addEventListener('click', (event) => {
      event.preventDefault();
    });
    fireEvent.click(link);
    expect(await screen.findByText('Export failed')).toBeTruthy();
    await vi.waitFor(() => {
      expect(screen.queryByText('Export ready')).toBeNull();
    });

    fireEvent.click(within(screen.getByRole('alert')).getByRole('button', { name: 'Retry' }));

    expect(retry).toHaveBeenCalledOnce();
    await vi.waitFor(() => {
      expect(screen.queryByText('Export failed')).toBeNull();
    });
  });
});
