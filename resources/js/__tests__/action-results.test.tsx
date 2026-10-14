import { __panel, actionModal, on, type ActionModalProps } from '@lathephp/core';
import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ResponseModal } from '../actions/ResponseModal';
import { applyActionResult, type ActionResult } from '../actions/result';

afterEach(() => {
  vi.restoreAllMocks();
  __panel.toasts.set([]);
});

function result(effect: ActionResult['effect'], message: string | null = null, level: ActionResult['level'] = 'success'): ActionResult {
  return { message, level, effect };
}

function handlers() {
  return { openModal: vi.fn(), refresh: vi.fn(), visit: vi.fn() };
}

describe('action results', () => {
  it('toasts the message at its level', () => {
    applyActionResult(result(null, 'Action was executed successfully.'), handlers());
    applyActionResult(result(null, 'Nope.', 'danger'), handlers());

    expect(__panel.toasts.get().map((toast) => [toast.type, toast.message])).toEqual([
      ['success', 'Action was executed successfully.'],
      ['error', 'Nope.'],
    ]);
  });

  it('opens a redirect in a new tab when asked', () => {
    const open = vi.spyOn(window, 'open').mockReturnValue(null);

    applyActionResult(result({ type: 'redirect', url: 'https://example.com/a', newTab: true }), handlers());

    expect(open).toHaveBeenCalledWith('https://example.com/a', '_blank', 'noopener');
  });

  it('visits through Inertia', () => {
    const on = handlers();

    applyActionResult(result({ type: 'visit', path: '/resources/orders', options: { preserveScroll: true } }), on);

    expect(on.visit).toHaveBeenCalledWith('/resources/orders', { preserveScroll: true });
  });

  it('saves a download under its name', () => {
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined);

    applyActionResult(result({ type: 'download', name: 'orders.csv', url: '/lathe/api/exports/1/download' }), handlers());

    const anchor = click.mock.contexts[0] as HTMLAnchorElement;
    expect(anchor.download).toBe('orders.csv');
    expect(anchor.getAttribute('href')).toBe('/lathe/api/exports/1/download');
  });

  it('dispatches an emit as a lathe: window event with its data', () => {
    const listener = vi.fn();
    window.addEventListener('lathe:import:headers-detected', listener);

    applyActionResult(result({ type: 'emit', event: 'import:headers-detected', data: { import_id: 3 } }), handlers());

    expect((listener.mock.calls[0]?.[0] as CustomEvent).detail).toEqual({ import_id: 3 });
    window.removeEventListener('lathe:import:headers-detected', listener);
  });

  it('sends an emit to the panel event bus too, so plugins hear it with on()', () => {
    const listener = vi.fn();
    const off = on('invoice:sent', listener);

    applyActionResult(result({ type: 'emit', event: 'invoice:sent', data: { id: 7 } }), handlers());

    expect(listener).toHaveBeenCalledWith({ id: 7 });
    off();
  });

  it('refreshes the list on deleted and opens a modal on modal', () => {
    const on = handlers();

    applyActionResult(result({ type: 'deleted' }, 'The resource was deleted.'), on);
    applyActionResult(result({ type: 'modal', component: 'hold-order', data: { id: 12 } }), on);

    expect(on.refresh).toHaveBeenCalledTimes(1);
    expect(on.openModal).toHaveBeenCalledWith('hold-order', { id: 12 });
    expect(__panel.toasts.get().map((toast) => toast.message)).toEqual(['The resource was deleted.']);
  });
});

describe('the response modal', () => {
  it('renders the component registered under the name', () => {
    function HoldOrder({ data }: ActionModalProps) {
      return <p>Hold order {String(data.id)}</p>;
    }
    actionModal('hold-order', HoldOrder);

    render(<ResponseModal component="hold-order" data={{ id: 12 }} onClose={vi.fn()} />);

    expect(screen.getByText('Hold order 12')).toBeTruthy();
  });

  it('toasts an unknown name and shows nothing else', () => {
    const onClose = vi.fn();

    const { container } = render(<ResponseModal component="missing" data={{}} onClose={onClose} />);

    expect(container.textContent).toBe('');
    expect(__panel.toasts.get().map((toast) => toast.message)).toEqual(['Unknown modal component']);
    expect(onClose).toHaveBeenCalled();
  });
});
