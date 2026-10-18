import { describe, expect, it } from 'vitest';
import type { WidgetDescriptor } from '../widgets/types';
import { addBack, hide, move, resize, toPayload } from '../widgets/customize/draft';

function card(uriKey: string, width = '1/4', columns = 3): WidgetDescriptor {
  return {
    name: uriKey,
    uriKey,
    component: 'value-widget',
    width,
    columns,
    height: 'fixed',
    help: null,
    helpWidth: 250,
    refreshEvery: 0,
    refreshWhenActionsRun: false,
    refreshWhenFiltersChange: false,
    onlyOnDetail: false,
    requestsValue: true,
    ranges: [],
  };
}

const keys = (list: WidgetDescriptor[]) => list.map((widget) => widget.uriKey);

describe('the layout draft', () => {
  it('moves a card to a later or an earlier place', () => {
    expect(move(['orders', 'revenue', 'status', 'paid'], 0, 1)).toEqual(['revenue', 'orders', 'status', 'paid']);
    expect(move(['orders', 'revenue', 'status', 'paid'], 3, 1)).toEqual(['orders', 'paid', 'revenue', 'status']);
  });

  it('sets a size with its columns', () => {
    const draft = resize({ shown: [card('orders'), card('status', '1/3', 4)], hidden: [] }, 'status', '1/2');

    expect(draft.shown.map((widget) => [widget.uriKey, widget.width, widget.columns])).toEqual([
      ['orders', '1/4', 3],
      ['status', '1/2', 6],
    ]);
  });

  it('hides a card at the end of the hidden ones and adds one back at the end of the page', () => {
    const hidden = hide({ shown: [card('orders'), card('paid')], hidden: [card('help')] }, 'orders');

    expect([keys(hidden.shown), keys(hidden.hidden)]).toEqual([['paid'], ['help', 'orders']]);

    const added = addBack(hidden, 'help');

    expect([keys(added.shown), keys(added.hidden)]).toEqual([['paid', 'help'], ['orders']]);
  });

  it('sends the shown cards in order, then the hidden ones', () => {
    expect(toPayload({ shown: [card('revenue'), card('status', '1/2', 6)], hidden: [card('paid')] })).toEqual([
      { uriKey: 'revenue', width: '1/4', hidden: false },
      { uriKey: 'status', width: '1/2', hidden: false },
      { uriKey: 'paid', width: '1/4', hidden: true },
    ]);
  });
});
