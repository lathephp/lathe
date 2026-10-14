// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '../collapsible';
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from '../index';

afterEach(cleanup);

describe('Accordion', () => {
  it('opens the panel of the trigger clicked', () => {
    render(
      <Accordion>
        <AccordionItem value="shipping">
          <AccordionTrigger>Shipping</AccordionTrigger>
          <AccordionContent>Ships in two days.</AccordionContent>
        </AccordionItem>
        <AccordionItem value="returns">
          <AccordionTrigger>Returns</AccordionTrigger>
          <AccordionContent>Thirty days.</AccordionContent>
        </AccordionItem>
      </Accordion>,
    );
    const shipping = screen.getByRole('button', { name: 'Shipping' });

    expect(shipping.getAttribute('aria-expanded')).toBe('false');

    fireEvent.click(shipping);

    expect(shipping.getAttribute('aria-expanded')).toBe('true');
    expect(
      screen.getByText('Ships in two days.').closest('[data-slot="accordion-content"]')?.hasAttribute('data-open'),
    ).toBe(true);
    expect(screen.getByRole('button', { name: 'Returns' }).getAttribute('aria-expanded')).toBe('false');
  });
});

describe('Collapsible', () => {
  it('shows its content when the trigger is clicked', () => {
    render(
      <Collapsible>
        <CollapsibleTrigger>Details</CollapsibleTrigger>
        <CollapsibleContent>Hidden detail</CollapsibleContent>
      </Collapsible>,
    );

    expect(screen.queryByText('Hidden detail')).toBeNull();

    fireEvent.click(screen.getByRole('button', { name: 'Details' }));

    expect(screen.getByText('Hidden detail').getAttribute('data-slot')).toBe('collapsible-content');
  });
});

describe('Tabs', () => {
  it('shows the panel of the tab chosen with the arrow keys', async () => {
    render(
      <Tabs defaultValue="details">
        <TabsList variant="line">
          <TabsTrigger value="details">Details</TabsTrigger>
          <TabsTrigger value="history">History</TabsTrigger>
        </TabsList>
        <TabsContent value="details">Order details</TabsContent>
        <TabsContent value="history">Order history</TabsContent>
      </Tabs>,
    );
    const details = screen.getByRole('tab', { name: 'Details' });

    expect(details.getAttribute('aria-selected')).toBe('true');
    expect(screen.getByRole('tabpanel').textContent).toBe('Order details');

    details.focus();
    fireEvent.keyDown(details, { key: 'ArrowRight' });

    await waitFor(() => {
      expect(document.activeElement).toBe(screen.getByRole('tab', { name: 'History' }));
    });
    expect(screen.getByRole('tablist').getAttribute('data-variant')).toBe('line');
  });
});
